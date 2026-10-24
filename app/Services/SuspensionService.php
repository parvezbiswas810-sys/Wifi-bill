<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InternetSuspensionLog;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Suspends internet access for customers with unpaid invoices.
 *
 * Suspension only happens after RouterOS confirms the PPP secret is disabled,
 * and the fact that billing owns the suspension is recorded so that a later
 * restoration never re-enables an account an operator disabled by hand.
 */
class SuspensionService
{
    public function __construct(protected MikrotikService $mikrotik)
    {
    }

    /**
     * Suspends every customer whose suspension date has arrived.
     *
     * @return array{suspended: int, skipped: int, failed: int, details: array<int, string>}
     */
    public function suspendDueCustomers(?int $ispId = null): array
    {
        $suspended = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        $query = Customer::query()
            ->withoutGlobalScope('isp')
            ->where('billing_enabled', true)
            ->where('status', 'active')
            ->where('auto_suspend', true)
            ->whereNotNull('next_suspension_date')
            ->whereDate('next_suspension_date', '<=', now()->toDateString());

        if ($ispId !== null) {
            $query->where('isp_id', $ispId);
        }

        foreach ($query->cursor() as $customer) {
            $result = bind_isp($customer->isp_id, fn () => $this->suspendIfUnpaid($customer));

            if ($result['status'] === 'suspended') {
                $suspended++;
            } elseif ($result['status'] === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }
            $details[] = "{$customer->customer_code}: {$result['message']}";
        }

        return compact('suspended', 'skipped', 'failed', 'details');
    }

    /**
     * Evaluates one customer and suspends only when genuinely eligible.
     *
     * @return array{status: string, message: string}
     */
    public function suspendIfUnpaid(Customer $customer): array
    {
        if (! $customer->billing_enabled) {
            return ['status' => 'skipped', 'message' => 'billing disabled'];
        }
        if (! $customer->auto_suspend) {
            return ['status' => 'skipped', 'message' => 'auto suspend off'];
        }
        $dueAt = $this->suspensionDueAt($customer);

        if ($dueAt === null || $dueAt->isFuture()) {
            return ['status' => 'skipped', 'message' => 'suspension time not reached'];
        }

        // A customer with nothing outstanding must never be suspended.
        $overdue = $customer->outstandingInvoices()
            ->whereDate('due_date', '<=', now()->toDateString())
            ->get();

        if ($overdue->isEmpty()) {
            return ['status' => 'skipped', 'message' => 'no overdue invoice'];
        }

        if ($customer->internet_status === 'suspended') {
            return ['status' => 'skipped', 'message' => 'already suspended'];
        }

        $invoice = $overdue->sortBy('due_date')->first();

        $result = $this->mikrotik->suspendCustomer($customer);

        if ($result['verified']) {
            $customer->forceFill([
                'internet_status' => 'suspended',
                'suspension_owned_by_billing' => true,
                'last_suspended_at' => now(),
            ])->save();
        }

        if (! $result['verified']) {
            AppNotification::notifyAdmin(
                $customer->isp_id,
                'suspension_failed',
                'Internet suspension failed',
                "{$customer->name} ({$customer->customer_code}): {$result['message']}",
                ['customer_id' => $customer->id],
                'danger'
            );

            return ['status' => 'failed', 'message' => $result['message']];
        }

        AppNotification::notifyAdmin(
            $customer->isp_id,
            'internet_suspended',
            'Internet suspended',
            "{$customer->name} ({$customer->customer_code}) suspended for unpaid invoice {$invoice->invoice_number}",
            ['customer_id' => $customer->id, 'invoice_id' => $invoice->id],
            'warning'
        );

        AppNotification::notifyCustomer(
            $customer->isp_id,
            $customer->id,
            'internet_suspended',
            'Internet service suspended',
            'Your internet has been suspended because invoice '.$invoice->invoice_number.' remains unpaid.',
            ['invoice_id' => $invoice->id],
            'danger'
        );

        return ['status' => 'suspended', 'message' => 'suspended for '.$invoice->invoice_number];
    }

    /**
     * Restores a customer after a payment settles their billing condition.
     *
     * Restoration happens only when this system owns the suspension and no
     * other invoice still requires the account to stay down.
     *
     * @return array{status: string, message: string}
     */
    public function restoreIfEligible(Customer $customer): array
    {
        $settings = $customer->isp?->billingSetting;

        if ($settings && ! $settings->auto_restore_enabled) {
            return ['status' => 'skipped', 'message' => 'auto restore disabled'];
        }

        if ($customer->internet_status !== 'suspended') {
            return ['status' => 'skipped', 'message' => 'not suspended'];
        }

        // Never touch an account that an operator disabled outside billing.
        if (! $customer->suspension_owned_by_billing) {
            return ['status' => 'skipped', 'message' => 'suspension not owned by billing'];
        }

        $stillOwed = $customer->outstandingInvoices()
            ->whereDate('due_date', '<=', now()->toDateString())
            ->exists();

        if ($stillOwed) {
            return ['status' => 'skipped', 'message' => 'another overdue invoice remains'];
        }

        $result = $this->mikrotik->restoreCustomer($customer);

        if ($result['verified']) {
            $customer->forceFill([
                'internet_status' => 'active',
                'suspension_owned_by_billing' => false,
                'last_restored_at' => now(),
            ])->save();
        }

        if (! $result['verified']) {
            AppNotification::notifyAdmin(
                $customer->isp_id,
                'restoration_failed',
                'Internet restoration failed',
                "{$customer->name} ({$customer->customer_code}): {$result['message']}",
                ['customer_id' => $customer->id],
                'danger'
            );

            return ['status' => 'failed', 'message' => $result['message']];
        }

        $customer->forceFill(['next_suspension_date' => null])->save();

        AppNotification::notifyAdmin(
            $customer->isp_id,
            'internet_restored',
            'Internet restored',
            "{$customer->name} ({$customer->customer_code}) restored after payment",
            ['customer_id' => $customer->id],
            'success'
        );

        AppNotification::notifyCustomer(
            $customer->isp_id,
            $customer->id,
            'internet_restored',
            'Internet restored',
            'Your internet service has been restored. Thank you for your payment.',
            [],
            'success'
        );

        return ['status' => 'restored', 'message' => 'internet restored'];
    }

    /**
     * Manually suspend or restore from the admin panel.
     *
     * @return array{ok: bool, message: string}
     */
    public function manualToggle(Customer $customer, bool $suspend, string $reason): array
    {
        if ($suspend) {
            $result = $this->mikrotik->suspendCustomer($customer);
        } else {
            $result = $this->mikrotik->restoreCustomer($customer);
        }

        // A manual action always takes ownership of the resulting state.
        if ($result['verified']) {
            $customer->forceFill($suspend
                ? [
                    'internet_status' => 'suspended',
                    'suspension_owned_by_billing' => true,
                    'last_suspended_at' => now(),
                ]
                : [
                    'internet_status' => 'active',
                    'suspension_owned_by_billing' => false,
                    'last_restored_at' => now(),
                ]
            )->save();
        }

        InternetSuspensionLog::create([
            'isp_id' => $customer->isp_id,
            'customer_id' => $customer->id,
            'router_id' => $customer->router_id,
            'action' => $suspend ? 'suspend' : 'restore',
            'status' => $result['verified'] ? 'success' : 'failed',
            'reason' => $reason,
            'message' => $result['message'],
            'performed_by' => 'admin',
            'user_id' => auth()->id(),
        ]);

        AuditLog::record(
            $suspend ? 'customer.manual_suspend' : 'customer.manual_restore',
            $customer,
            ($suspend ? 'Suspended' : 'Restored')." internet for {$customer->name}: {$reason}",
            new: ['reason' => $reason]
        );

        return ['ok' => $result['verified'], 'message' => $result['message']];
    }

    /**
     * The exact moment suspension becomes due for this customer.
     *
     * The date comes from the customer, the time of day from the customer's own
     * setting or the ISP billing policy, and both are interpreted in the ISP
     * timezone so a configured midnight suspension happens at local midnight.
     * The returned instant is converted to the application timezone for
     * comparison against now().
     */
    public function suspensionDueAt(Customer $customer): ?CarbonInterface
    {
        if (! $customer->next_suspension_date) {
            return null;
        }

        $timezone = $customer->isp?->timezone ?? 'Asia/Dhaka';
        // Customer override first, then the ISP billing policy.
        $time = $customer->suspension_time
            ?: ($customer->isp?->billingSetting?->suspension_time ?? '00:00:00');

        $date = $customer->next_suspension_date->format('Y-m-d');
        $time = substr((string) $time, 0, 8);

        return CarbonImmutable::parse("{$date} {$time}", $timezone)->setTimezone(config('app.timezone'));
    }
}
