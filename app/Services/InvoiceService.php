<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates recurring invoices.
 *
 * Invoice generation is idempotent: the (customer_id, period_key) unique index
 * is the source of truth, so re-running the scheduler can never double-bill a
 * customer for the same billing period.
 */
class InvoiceService
{
    /**
     * Creates invoices for every customer whose schedule is due.
     *
     * @return array{created: int, skipped: int, failed: int, details: array<int, string>}
     */
    public function generateDueInvoices(?int $ispId = null): array
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        $query = Customer::query()
            ->withoutGlobalScope('isp')
            ->where('billing_enabled', true)
            ->where('status', 'active')
            ->whereNotNull('next_bill_date');

        if ($ispId !== null) {
            $query->where('isp_id', $ispId);
        }

        foreach ($query->cursor() as $customer) {
            try {
                $result = bind_isp($customer->isp_id, function () use ($customer) {
                    return $this->generateForCustomer($customer);
                });

                if ($result['created']) {
                    $created++;
                    $details[] = "{$customer->customer_code}: {$result['invoice_number']}";
                } else {
                    $skipped++;
                    if ($result['reason']) {
                        $details[] = "{$customer->customer_code}: {$result['reason']}";
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                $details[] = "{$customer->customer_code}: ERROR ".$e->getMessage();
            }
        }

        return compact('created', 'skipped', 'failed', 'details');
    }

    /**
     * Generates one invoice for a customer if their schedule is due.
     *
     * @return array{created: bool, invoice_number: ?string, reason: ?string}
     */
    public function generateForCustomer(Customer $customer): array
    {
        if (! $customer->billing_enabled) {
            return ['created' => false, 'invoice_number' => null, 'reason' => 'billing disabled'];
        }
        if ($customer->status !== 'active') {
            return ['created' => false, 'invoice_number' => null, 'reason' => 'customer not active'];
        }
        if (! $customer->next_bill_date) {
            return ['created' => false, 'invoice_number' => null, 'reason' => 'no schedule'];
        }

        $settings = $this->settingsFor($customer->isp_id);
        $tz = $customer->isp?->timezone ?? $settings->timezone ?? 'Asia/Dhaka';

        $dueAt = Carbon::parse(
            $customer->next_bill_date->toDateString().' '.($customer->bill_generation_time ?? '00:05:00'),
            $tz
        );

        if ($dueAt->isFuture()) {
            return ['created' => false, 'invoice_number' => null, 'reason' => 'not due yet'];
        }

        $periodStart = $customer->next_bill_date->copy()->startOfDay();
        $periodKey = $periodStart->format('Y-m');

        // Guard against a duplicate for the same billing period.
        $existing = Invoice::withoutGlobalScope('isp')
            ->where('customer_id', $customer->id)
            ->where('period_key', $periodKey)
            ->first();

        if ($existing) {
            $this->advanceSchedule($customer, $periodStart);

            return ['created' => false, 'invoice_number' => null, 'reason' => "invoice {$existing->invoice_number} already exists"];
        }

        $amount = (float) $customer->monthly_bill_amount;
        if ($amount <= 0) {
            $this->advanceSchedule($customer, $periodStart);

            return ['created' => false, 'invoice_number' => null, 'reason' => 'bill amount is zero'];
        }

        $dueDays = $customer->due_days ?? $settings->default_due_days ?? 10;
        $graceDays = $customer->grace_period_days ?? $settings->grace_period_days ?? 3;

        $periodEnd = $this->periodEnd($periodStart, $settings->default_billing_cycle ?? 'monthly');
        $issueDate = $periodStart->copy();
        $dueDate = $issueDate->copy()->addDays($dueDays);
        $suspensionDate = $dueDate->copy()->addDays($graceDays);

        $invoice = DB::transaction(function () use (
            $customer, $amount, $periodStart, $periodEnd, $periodKey,
            $issueDate, $dueDate, $suspensionDate, $tz
        ) {
            $invoice = Invoice::create([
                'isp_id' => $customer->isp_id,
                'customer_id' => $customer->id,
                'invoice_number' => $this->nextInvoiceNumber($customer),
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'period_key' => $periodKey,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'suspension_date' => $suspensionDate->toDateString(),
                'amount' => $amount,
                'amount_paid' => 0,
                'balance' => $amount,
                'status' => Invoice::STATUS_UNPAID,
                'generated_by' => app()->runningInConsole() ? 'system' : 'admin',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Internet service - '.$periodStart->format('F Y'),
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
            ]);

            $customer->forceFill([
                'last_billed_on' => $periodStart->toDateString(),
                'next_suspension_date' => $suspensionDate->toDateString(),
            ])->save();

            $this->advanceSchedule($customer, $periodStart);

            return $invoice;
        });

        AuditLog::create([
            'isp_id' => $customer->isp_id,
            'actor_type' => 'system',
            'action' => 'invoice.generated',
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'description' => "Invoice {$invoice->invoice_number} generated for {$customer->name}",
            'new_values' => ['amount' => $amount, 'period' => $periodKey],
        ]);

        $settings = $this->settingsFor($customer->isp_id);
        if ($settings->notify_on_invoice) {
            AppNotification::notifyCustomer(
                $customer->isp_id,
                $customer->id,
                'invoice_generated',
                'New invoice generated',
                "Invoice {$invoice->invoice_number} for ".isp_money($amount).' is due on '.$dueDate->format('d M Y').'.',
                ['invoice_id' => $invoice->id]
            );
            AppNotification::notifyAdmin(
                $customer->isp_id,
                'invoice_generated',
                'Invoice generated',
                "{$customer->name} ({$customer->customer_code}) - {$invoice->invoice_number} ".isp_money($amount),
                ['invoice_id' => $invoice->id]
            );
        }

        return ['created' => true, 'invoice_number' => $invoice->invoice_number, 'reason' => null];
    }

    /** Moves the customer's schedule forward one billing cycle. */
    protected function advanceSchedule(Customer $customer, Carbon $from): void
    {
        $day = (int) ($customer->bill_generation_day ?: 1);
        $next = $from->copy()->addMonthNoOverflow()->day(min($day, $from->copy()->addMonthNoOverflow()->daysInMonth));

        $customer->forceFill(['next_bill_date' => $next->toDateString()])->save();
    }

    protected function periodEnd(Carbon $start, string $cycle): Carbon
    {
        return match ($cycle) {
            'weekly' => $start->copy()->addWeek()->subDay(),
            'quarterly' => $start->copy()->addMonths(3)->subDay(),
            'yearly' => $start->copy()->addYear()->subDay(),
            default => $start->copy()->addMonthNoOverflow()->subDay(),
        };
    }

    protected function nextInvoiceNumber(Customer $customer): string
    {
        $prefix = $customer->isp?->invoice_prefix ?: 'INV';
        $year = now()->format('Y');
        $sequence = Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $customer->isp_id)
            ->whereYear('created_at', $year)
            ->count() + 1;

        do {
            $candidate = sprintf('%s-%s-%05d', $prefix, $year, $sequence);
            $exists = Invoice::withoutGlobalScope('isp')->where('invoice_number', $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    protected function settingsFor(int $ispId): BillingSetting
    {
        return BillingSetting::withoutGlobalScope('isp')->firstOrCreate(
            ['isp_id' => $ispId],
            []
        );
    }
}
