<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records money against invoices.
 *
 * Approving a submission is transactional and idempotent: the payment row is
 * linked to the submission through a unique column, so a repeated approval
 * click cannot record the same payment twice.
 */
class PaymentService
{
    public function __construct(protected SuspensionService $suspension)
    {
    }

    /** Creates a pending payment submission from a customer. */
    public function submit(Customer $customer, Invoice $invoice, array $data): PaymentSubmission
    {
        if ($invoice->customer_id !== $customer->id) {
            throw ValidationException::withMessages(['invoice' => 'This invoice does not belong to you.']);
        }
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['invoice' => 'This invoice has been cancelled.']);
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }
        if ($amount > (float) $invoice->balance + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'The amount cannot exceed the outstanding balance of '.number_format((float) $invoice->balance, 2).'.',
            ]);
        }

        $reference = $data['reference'] ?? null;
        $methodId = $data['payment_method_id'] ?? null;

        // Reject a re-used transaction reference for the same method.
        if ($reference) {
            $duplicate = PaymentSubmission::withoutGlobalScope('isp')
                ->where('isp_id', $customer->isp_id)
                ->where('payment_method_id', $methodId)
                ->where('reference', $reference)
                ->whereIn('status', [PaymentSubmission::STATUS_PENDING, PaymentSubmission::STATUS_APPROVED])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference' => 'This transaction reference has already been submitted.',
                ]);
            }
        }

        $submission = PaymentSubmission::create([
            'isp_id' => $customer->isp_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $methodId,
            'method_name' => $data['method_name'] ?? null,
            'amount' => $amount,
            'sender_number' => $data['sender_number'] ?? null,
            'reference' => $reference,
            'screenshot_path' => $data['screenshot_path'] ?? null,
            'status' => PaymentSubmission::STATUS_PENDING,
        ]);

        AppNotification::notifyAdmin(
            $customer->isp_id,
            'payment_submitted',
            'Payment submitted',
            "{$customer->name} submitted ".isp_money($amount)." for invoice {$invoice->invoice_number} (awaiting verification)",
            ['submission_id' => $submission->id, 'invoice_id' => $invoice->id],
            'info'
        );

        AppNotification::notifyCustomer(
            $customer->isp_id,
            $customer->id,
            'payment_submitted',
            'Payment submitted',
            'Your payment of '.isp_money($amount).' is awaiting administrator verification.',
            ['submission_id' => $submission->id],
            'info'
        );

        return $submission;
    }

    /**
     * Approves a submission and records the payment exactly once.
     *
     * @return array{ok: bool, message: string, payment?: Payment, restored?: bool}
     */
    public function approve(PaymentSubmission $submission, User $admin, ?float $approvedAmount = null, ?string $note = null): array
    {
        return DB::transaction(function () use ($submission, $admin, $approvedAmount, $note) {
            // Lock the row so two concurrent approvals cannot both proceed.
            $locked = PaymentSubmission::withoutGlobalScope('isp')
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return ['ok' => false, 'message' => 'Submission not found.'];
            }
            if ($locked->status === PaymentSubmission::STATUS_APPROVED || $locked->payment_id) {
                return ['ok' => false, 'message' => 'This submission has already been approved.'];
            }

            $invoice = Invoice::withoutGlobalScope('isp')->whereKey($locked->invoice_id)->lockForUpdate()->first();
            if (! $invoice) {
                return ['ok' => false, 'message' => 'Invoice not found.'];
            }

            $amount = round($approvedAmount ?? (float) $locked->amount, 2);
            if ($amount <= 0) {
                return ['ok' => false, 'message' => 'Approved amount must be greater than zero.'];
            }

            // Never credit more than the invoice still owes.
            $amount = min($amount, (float) $invoice->balance);

            $payment = Payment::create([
                'isp_id' => $locked->isp_id,
                'invoice_id' => $invoice->id,
                'customer_id' => $locked->customer_id,
                'payment_method_id' => $locked->payment_method_id,
                'submission_id' => $locked->id,
                'method_name' => $locked->method_name,
                'amount' => $amount,
                'sender_number' => $locked->sender_number,
                'reference' => $locked->reference,
                'source' => 'submission',
                'recorded_by' => $admin->id,
                'paid_at' => now(),
                'notes' => $note,
            ]);

            $locked->forceFill([
                'status' => PaymentSubmission::STATUS_APPROVED,
                'payment_id' => $payment->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
                'rejection_reason' => null,
            ])->save();

            $invoice->recalculate();

            AuditLog::create([
                'isp_id' => $locked->isp_id,
                'actor_type' => 'user',
                'actor_id' => $admin->id,
                'action' => 'payment.approved',
                'auditable_type' => Payment::class,
                'auditable_id' => $payment->id,
                'description' => "Approved ".isp_money($amount)." for invoice {$invoice->invoice_number}",
                'new_values' => [
                    'amount' => $amount,
                    'submission_id' => $locked->id,
                    'invoice_balance' => (float) $invoice->balance,
                ],
                'ip_address' => request()->ip(),
            ]);

            $customer = Customer::withoutGlobalScope('isp')->whereKey($locked->customer_id)->first();

            AppNotification::notifyCustomer(
                $locked->isp_id,
                $locked->customer_id,
                'payment_approved',
                'Payment approved',
                'Your payment of '.isp_money($amount).' for invoice '.$invoice->invoice_number.' has been approved.',
                ['invoice_id' => $invoice->id, 'payment_id' => $payment->id],
                'success'
            );

            // Restoration is only attempted once the invoice is fully settled.
            $restored = false;
            if ($customer && (float) $invoice->balance <= 0) {
                $result = $this->suspension->restoreIfEligible($customer);
                $restored = $result['status'] === 'restored';
            }

            return [
                'ok' => true,
                'message' => 'Payment approved for '.isp_money($amount).'.',
                'payment' => $payment,
                'restored' => $restored,
            ];
        });
    }

    /** Rejects a submission with a required reason. */
    public function reject(PaymentSubmission $submission, User $admin, string $reason): array
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        return DB::transaction(function () use ($submission, $admin, $reason) {
            $locked = PaymentSubmission::withoutGlobalScope('isp')
                ->whereKey($submission->id)->lockForUpdate()->first();

            if (! $locked) {
                return ['ok' => false, 'message' => 'Submission not found.'];
            }
            if ($locked->status === PaymentSubmission::STATUS_APPROVED) {
                return ['ok' => false, 'message' => 'An approved payment cannot be rejected.'];
            }

            $locked->forceFill([
                'status' => PaymentSubmission::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            AuditLog::create([
                'isp_id' => $locked->isp_id,
                'actor_type' => 'user',
                'actor_id' => $admin->id,
                'action' => 'payment.rejected',
                'auditable_type' => PaymentSubmission::class,
                'auditable_id' => $locked->id,
                'description' => 'Rejected payment submission: '.$reason,
                'ip_address' => request()->ip(),
            ]);

            AppNotification::notifyCustomer(
                $locked->isp_id,
                $locked->customer_id,
                'payment_rejected',
                'Payment rejected',
                'Your payment submission was rejected. Reason: '.$reason,
                ['submission_id' => $locked->id],
                'danger'
            );

            return ['ok' => true, 'message' => 'Submission rejected.'];
        });
    }

    /** Requests more information from the customer. */
    public function requestInfo(PaymentSubmission $submission, User $admin, string $note): array
    {
        $submission->forceFill([
            'status' => PaymentSubmission::STATUS_INFO_REQUESTED,
            'admin_note' => $note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        AppNotification::notifyCustomer(
            $submission->isp_id,
            $submission->customer_id,
            'payment_info_requested',
            'More information needed',
            $note,
            ['submission_id' => $submission->id],
            'warning'
        );

        return ['ok' => true, 'message' => 'Information requested from customer.'];
    }

    /** Records an offline/manual payment against an invoice. */
    public function recordManual(Invoice $invoice, User $admin, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $admin, $data) {
            $locked = Invoice::withoutGlobalScope('isp')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $amount = min(round((float) $data['amount'], 2), (float) $locked->balance);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
            }

            $payment = Payment::create([
                'isp_id' => $locked->isp_id,
                'invoice_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'method_name' => $data['method_name'] ?? 'Manual',
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'sender_number' => $data['sender_number'] ?? null,
                'source' => 'manual',
                'recorded_by' => $admin->id,
                'paid_at' => isset($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->recalculate();

            AuditLog::create([
                'isp_id' => $locked->isp_id,
                'actor_type' => 'user',
                'actor_id' => $admin->id,
                'action' => 'payment.manual_recorded',
                'auditable_type' => Payment::class,
                'auditable_id' => $payment->id,
                'description' => "Recorded manual payment ".isp_money($amount)." on invoice {$locked->invoice_number}",
                'new_values' => ['amount' => $amount],
                'ip_address' => request()->ip(),
            ]);

            return $payment;
        });
    }
}
