<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\IspProfile;
use App\Models\MikrotikRouter;
use App\Models\PppAccount;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MikrotikService;
use App\Services\PaymentService;
use App\Services\SuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full billing lifecycle, using a router stub that answers the way a real
 * RouterOS device would.
 *
 * This is the test that proves the pieces work together: an overdue customer is
 * suspended, pays, and is restored - and every intermediate state is checked.
 */
class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected IspProfile $isp;

    protected MikrotikRouter $router;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isp = IspProfile::create([
            'name' => 'Lifecycle ISP', 'slug' => 'lifecycle', 'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT', 'invoice_prefix' => 'INV',
        ]);
        BillingSetting::create([
            'isp_id' => $this->isp->id, 'default_bill_amount' => 500,
            'default_due_days' => 10, 'grace_period_days' => 3,
            'suspension_time' => '00:00:00',
            'auto_suspend_enabled' => true, 'auto_restore_enabled' => true,
        ]);

        $this->admin = User::create([
            'isp_id' => $this->isp->id, 'name' => 'Admin', 'username' => 'lcadmin',
            'email' => 'lc@test.local', 'password' => 'password123', 'role' => 'isp_admin',
            'is_active' => true,
        ]);

        $this->router = MikrotikRouter::create([
            'isp_id' => $this->isp->id, 'name' => 'LC Router', 'host' => '192.0.2.50',
            'api_port' => 8728, 'api_username' => 'api', 'api_password' => 'x',
            // Marked online so status labels reflect billing state, not transport.
            'last_status' => 'online', 'last_checked_at' => now(),
        ]);
    }

    /** Replaces the router service for every consumer, including PaymentService. */
    protected function useRouter(MikrotikService $router): MikrotikService
    {
        $this->app->instance(MikrotikService::class, $router);

        return $router;
    }

    /**
     * A RouterOS stand-in that records every disable/enable and can be told to
     * fail, so the "never claim success without confirmation" rule is testable.
     */
    protected function fakeRouter(bool $failOperations = false): MikrotikService
    {
        return new class($failOperations) extends MikrotikService
        {
            /** @var array<int, string> */
            public array $calls = [];

            public function __construct(protected bool $failing)
            {
                // Deliberately skip the parent constructor: no HTTP client needed.
            }

            public function suspendCustomer(Customer $customer): array
            {
                $this->calls[] = 'suspend:'.$customer->ppp_username;

                return $this->failing
                    ? ['ok' => false, 'message' => 'connect() failed: host unreachable', 'verified' => false]
                    : ['ok' => true, 'message' => 'PPP secret disabled and confirmed', 'verified' => true];
            }

            public function restoreCustomer(Customer $customer): array
            {
                $this->calls[] = 'restore:'.$customer->ppp_username;

                return $this->failing
                    ? ['ok' => false, 'message' => 'connect() failed: host unreachable', 'verified' => false]
                    : ['ok' => true, 'message' => 'PPP secret enabled and confirmed', 'verified' => true];
            }
        };
    }

    protected function makeCustomer(): Customer
    {
        $ppp = PppAccount::create([
            'isp_id' => $this->isp->id, 'router_id' => $this->router->id,
            'username' => 'lifecycleppp', 'router_secret_id' => '*7',
            'profile' => '10Mbps', 'disabled' => false, 'is_active_session' => true,
        ]);

        return Customer::create([
            'isp_id' => $this->isp->id, 'customer_code' => 'CLC1', 'name' => 'Lifecycle Customer',
            'username' => 'lccust', 'password' => 'custpass123', 'phone' => '01700000009',
            'router_id' => $this->router->id, 'ppp_account_id' => $ppp->id, 'ppp_username' => $ppp->username,
            'monthly_bill_amount' => 500, 'billing_enabled' => true, 'auto_suspend' => true,
            'billing_start_date' => now()->startOfMonth()->toDateString(),
            'bill_generation_day' => 1, 'bill_generation_time' => '00:05:00',
            'next_bill_date' => now()->subDay()->toDateString(),
            'due_days' => 10, 'grace_period_days' => 3,
        ]);
    }

    /** Puts the customer's invoice overdue and the suspension moment in the past. */
    protected function makeOverdue(Customer $customer): void
    {
        bind_isp($this->isp->id, function () use ($customer) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $customer->invoices()->update(['due_date' => now()->subDays(6)->toDateString()]);
            $customer->forceFill([
                'next_suspension_date' => now()->subDay()->toDateString(),
                'suspension_time' => '00:00:00',
            ])->save();
        });
    }

    public function test_full_lifecycle_suspend_then_pay_then_restore(): void
    {
        $customer = $this->makeCustomer();
        $router = $this->useRouter($this->fakeRouter());

        // 1. Invoice the customer, then let the invoice go overdue.
        $this->makeOverdue($customer);

        $invoice = $customer->fresh()->invoices()->firstOrFail();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(500.0, (float) $invoice->balance);

        // 2. The suspension run disables the PPP account and records ownership.
        $suspension = new SuspensionService($router);
        $result = bind_isp($this->isp->id, fn () => $suspension->suspendIfUnpaid($customer->fresh()));

        $this->assertSame('suspended', $result['status']);
        $this->assertSame(['suspend:lifecycleppp'], $router->calls);

        $customer = $customer->fresh();
        $this->assertSame('suspended', $customer->internet_status);
        $this->assertTrue((bool) $customer->suspension_owned_by_billing);
        $this->assertNotNull($customer->last_suspended_at);

        // The customer now reads as suspended, not merely offline.
        $this->assertSame('Suspended', $customer->internetStatusLabel());

        // 3. The customer submits a payment. It must not change anything yet.
        $paymentService = app(PaymentService::class);
        $submission = bind_isp($this->isp->id, fn () => $paymentService->submit(
            $customer->fresh(), $invoice, ['amount' => 500, 'reference' => 'LIFECYCLE-TXN-1']
        ));

        $this->assertSame('pending', $submission->status);
        $this->assertSame(0.0, (float) $customer->fresh()->payments()->sum('amount'));
        $this->assertSame(500.0, (float) $invoice->fresh()->balance);
        $this->assertSame('suspended', $customer->fresh()->internet_status,
            'A pending payment must not restore service');

        // 4. The administrator approves. The invoice settles and the account returns.
        $approval = bind_isp($this->isp->id, fn () => $paymentService->approve($submission, $this->admin));

        $this->assertTrue($approval['ok']);
        $this->assertTrue($approval['restored'], 'Restoration should have been attempted and confirmed');
        $this->assertSame(['suspend:lifecycleppp', 'restore:lifecycleppp'], $router->calls);

        $customer = $customer->fresh();
        $invoice = $invoice->fresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame(500.0, (float) $invoice->amount_paid);
        $this->assertSame(500.0, (float) $customer->payments()->sum('amount'));
        $this->assertSame('active', $customer->internet_status);
        $this->assertFalse((bool) $customer->suspension_owned_by_billing);
        $this->assertNotNull($customer->last_restored_at);

        // 5. Both events are in the audit trail.
        $this->assertDatabaseHas('internet_suspension_logs', [
            'customer_id' => $customer->id, 'action' => 'suspend', 'status' => 'success',
        ]);
        $this->assertDatabaseHas('internet_suspension_logs', [
            'customer_id' => $customer->id, 'action' => 'restore', 'status' => 'success',
        ]);
    }

    public function test_lifecycle_with_an_unreachable_router_leaves_the_customer_active(): void
    {
        $customer = $this->makeCustomer();
        $router = $this->useRouter($this->fakeRouter(failOperations: true));
        $this->makeOverdue($customer);

        $suspension = new SuspensionService($router);
        $result = bind_isp($this->isp->id, fn () => $suspension->suspendIfUnpaid($customer->fresh()));

        $this->assertSame('failed', $result['status']);

        $customer = $customer->fresh();
        $this->assertNotSame('suspended', $customer->internet_status,
            'An unreachable router must never result in a reported suspension');
        $this->assertFalse((bool) $customer->suspension_owned_by_billing);

        // The failure is surfaced to the administrator rather than hidden.
        $this->assertDatabaseHas('app_notifications', [
            'isp_id' => $this->isp->id, 'type' => 'suspension_failed',
        ]);
        $this->assertDatabaseHas('internet_suspension_logs', [
            'customer_id' => $customer->id, 'action' => 'suspend', 'status' => 'failed',
        ]);
    }

    public function test_a_second_invoice_blocks_restoration_after_a_partial_payment(): void
    {
        $customer = $this->makeCustomer();
        $router = $this->useRouter($this->fakeRouter());

        // Two unpaid invoices for the same customer.
        bind_isp($this->isp->id, function () use ($customer) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $first = $customer->invoices()->firstOrFail();

            // Force a second, distinct billing period.
            $second = $first->replicate(['invoice_number']);
            $second->invoice_number = $first->invoice_number.'-B';
            $second->period_key = $first->period_key.'-b';
            $second->billing_period_start = $first->billing_period_start->copy()->addMonth();
            $second->billing_period_end = $first->billing_period_end->copy()->addMonth();
            $second->issue_date = now()->toDateString();
            $second->due_date = now()->subDays(2)->toDateString();
            $second->amount = 500;
            $second->balance = 500;
            $second->amount_paid = 0;
            $second->status = 'unpaid';
            $second->save();

            // Make the customer genuinely due for suspension.
            $customer->forceFill([
                'next_suspension_date' => now()->subDay()->toDateString(),
                'suspension_time' => '00:00:00',
            ])->save();
        });

        $first = $customer->invoices()->orderBy('id')->firstOrFail();

        // Suspend, then settle only the first invoice.
        $suspension = new SuspensionService($router);
        bind_isp($this->isp->id, fn () => $suspension->suspendIfUnpaid($customer->fresh()));
        $this->assertSame('suspended', $customer->fresh()->internet_status);

        $paymentService = app(PaymentService::class);
        $submission = bind_isp($this->isp->id, fn () => $paymentService->submit(
            $customer->fresh(), $first, ['amount' => 500, 'reference' => 'PARTIAL-LIFE-1']
        ));
        $approval = bind_isp($this->isp->id, fn () => $paymentService->approve($submission, $this->admin));

        $this->assertTrue($approval['ok']);
        $this->assertFalse($approval['restored'], 'A remaining overdue invoice must block restoration');
        $this->assertSame('suspended', $customer->fresh()->internet_status);
        $this->assertSame(500.0, (float) $customer->fresh()->outstandingBalance());
    }

    public function test_manually_disabled_account_stays_disabled_through_the_whole_cycle(): void
    {
        $customer = $this->makeCustomer();
        $router = $this->useRouter($this->fakeRouter());
        $this->makeOverdue($customer);

        // An operator disabled this account by hand for an unrelated reason.
        $customer->forceFill([
            'internet_status' => 'suspended',
            'suspension_owned_by_billing' => false,
        ])->save();

        $suspension = new SuspensionService($router);

        // The suspension run must not touch it again.
        $result = bind_isp($this->isp->id, fn () => $suspension->suspendIfUnpaid($customer->fresh()));
        $this->assertSame('already suspended', $result['message']);
        $this->assertSame([], $router->calls);

        // Paying the invoice must not re-enable an account billing does not own.
        $invoice = $customer->invoices()->firstOrFail();
        $paymentService = app(PaymentService::class);
        $submission = bind_isp($this->isp->id, fn () => $paymentService->submit(
            $customer->fresh(), $invoice, ['amount' => 500, 'reference' => 'MANUAL-OWNED-1']
        ));
        $approval = bind_isp($this->isp->id, fn () => $paymentService->approve($submission, $this->admin));

        $this->assertTrue($approval['ok']);
        $this->assertFalse($approval['restored']);
        $this->assertSame([], $router->calls, 'No router call may happen for a suspension billing does not own');
        $this->assertSame('suspended', $customer->fresh()->internet_status);
    }
}
