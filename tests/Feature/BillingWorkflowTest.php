<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\IspProfile;
use App\Models\MikrotikRouter;
use App\Models\PaymentSubmission;
use App\Models\PppAccount;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MikrotikService;
use App\Services\PaymentService;
use App\Services\SuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Covers the billing, payment and suspension rules that must never regress.
 */
class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected IspProfile $isp;

    protected MikrotikRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isp = IspProfile::create([
            'name' => 'Test ISP', 'slug' => 'test-isp', 'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT', 'invoice_prefix' => 'INV',
        ]);

        BillingSetting::create([
            'isp_id' => $this->isp->id,
            'default_bill_amount' => 500,
            'default_due_days' => 10,
            'grace_period_days' => 3,
            'auto_suspend_enabled' => true,
            'auto_restore_enabled' => true,
        ]);

        $this->router = MikrotikRouter::create([
            'isp_id' => $this->isp->id,
            'name' => 'Test Router',
            'host' => '192.0.2.1',
            'api_port' => 8728,
            'api_username' => 'api',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);
    }

    /** Creates a customer bound to a PPP account on the test router. */
    protected function makeCustomer(array $overrides = []): Customer
    {
        $ppp = PppAccount::create([
            'isp_id' => $this->isp->id,
            'router_id' => $this->router->id,
            'username' => 'ppp'.uniqid(),
            'router_secret_id' => '*1',
            'disabled' => false,
            'is_active_session' => true,
        ]);

        return Customer::create(array_merge([
            'isp_id' => $this->isp->id,
            'customer_code' => 'C'.uniqid(),
            'name' => 'Test Customer',
            'phone' => '01700000000',
            'username' => 'cust'.uniqid(),
            'password' => 'secret123',
            'router_id' => $this->router->id,
            'ppp_account_id' => $ppp->id,
            'ppp_username' => $ppp->username,
            'monthly_bill_amount' => 500,
            'billing_enabled' => true,
            'billing_start_date' => now()->startOfMonth()->toDateString(),
            'bill_generation_day' => 1,
            'bill_generation_time' => '00:05:00',
            'next_bill_date' => now()->subDay()->toDateString(),
            'due_days' => 10,
            'grace_period_days' => 3,
            'status' => 'active',
            'internet_status' => 'active',
        ], $overrides));
    }

    public function test_invoice_is_generated_for_a_due_customer(): void
    {
        $customer = $this->makeCustomer();

        $result = bind_isp($this->isp->id, fn () => app(InvoiceService::class)->generateForCustomer($customer));

        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'amount' => 500,
            'status' => 'unpaid',
        ]);
    }

    public function test_rerunning_invoice_generation_does_not_create_duplicates(): void
    {
        $customer = $this->makeCustomer();

        bind_isp($this->isp->id, function () use ($customer) {
            $service = app(InvoiceService::class);
            $service->generateForCustomer($customer);
            $service->generateForCustomer($customer->fresh());
            $service->generateForCustomer($customer->fresh());
        });

        $this->assertSame(1, $customer->invoices()->count(), 'A billing period must only be invoiced once');
    }

    public function test_invoice_generation_is_idempotent_across_billing_runs(): void
    {
        $customer = $this->makeCustomer();

        // Simulate three consecutive scheduler runs on the same day.
        for ($i = 0; $i < 3; $i++) {
            $customer = $customer->fresh();
            bind_isp($this->isp->id, fn () => app(InvoiceService::class)->generateForCustomer($customer));
        }

        $this->assertSame(1, $customer->invoices()->count());
    }

    public function test_pending_payment_does_not_count_as_collected(): void
    {
        $customer = $this->makeCustomer();
        $invoice = bind_isp($this->isp->id, fn () => app(InvoiceService::class)->generateForCustomer($customer));

        $invoice = $customer->invoices()->first();

        bind_isp($this->isp->id, fn () => app(PaymentService::class)->submit($customer, $invoice, [
            'amount' => 500,
            'payment_method_id' => null,
            'reference' => 'TXN-PENDING-1',
        ]));

        $this->assertSame(0.0, (float) $customer->payments()->sum('amount'), 'Pending submissions are not collected money');
        $this->assertSame(500.0, (float) $invoice->fresh()->balance);
        $this->assertSame('unpaid', $invoice->fresh()->status);
    }

    public function test_approving_a_payment_records_it_once_even_when_clicked_twice(): void
    {
        $customer = $this->makeCustomer();
        $admin = User::create([
            'isp_id' => $this->isp->id, 'name' => 'Admin', 'username' => 'admin1',
            'email' => 'admin@test.local', 'password' => 'password123', 'role' => 'isp_admin',
        ]);

        bind_isp($this->isp->id, function () use ($customer, $admin) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $invoice = $customer->invoices()->firstOrFail();

            $service = app(PaymentService::class);

            $submission = $service->submit($customer, $invoice, [
                'amount' => 500, 'payment_method_id' => null, 'reference' => 'TXN-APPROVE-1',
            ]);

            $first = $service->approve($submission, $admin);
            $second = $service->approve($submission->fresh(), $admin);

            $this->assertTrue($first['ok']);
            $this->assertFalse($second['ok'], 'A second approval must be refused');
            $this->assertSame(1, $customer->payments()->count(), 'Exactly one payment row is recorded');
        });

        $this->assertSame(500.0, (float) $customer->payments()->sum('amount'));
    }

    public function test_partial_payment_leaves_the_correct_balance(): void
    {
        $customer = $this->makeCustomer();
        $admin = User::create([
            'isp_id' => $this->isp->id, 'name' => 'Admin', 'username' => 'admin2',
            'email' => 'admin2@test.local', 'password' => 'password123', 'role' => 'isp_admin',
        ]);

        bind_isp($this->isp->id, function () use ($customer, $admin) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $invoice = $customer->invoices()->firstOrFail();

            $service = app(PaymentService::class);
            $submission = $service->submit($customer, $invoice, [
                'amount' => 200, 'payment_method_id' => null, 'reference' => 'TXN-PARTIAL-1',
            ]);
            $service->approve($submission, $admin);

            $invoice->refresh();
            $this->assertSame(200.0, (float) $invoice->amount_paid);
            $this->assertSame(300.0, (float) $invoice->balance);
            $this->assertSame('partial', $invoice->status);
        });
    }

    public function test_duplicate_transaction_reference_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        bind_isp($this->isp->id, function () use ($customer) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $invoice = $customer->invoices()->firstOrFail();
            $service = app(PaymentService::class);

            $service->submit($customer, $invoice, ['amount' => 100, 'reference' => 'DUP-1']);

            $this->expectException(\Illuminate\Validation\ValidationException::class);
            $service->submit($customer, $invoice, ['amount' => 100, 'reference' => 'DUP-1']);
        });
    }

    public function test_customer_cannot_submit_payment_for_another_customers_invoice(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();

        bind_isp($this->isp->id, function () use ($owner, $other) {
            app(InvoiceService::class)->generateForCustomer($owner);
            $invoice = $owner->invoices()->firstOrFail();

            $this->expectException(\Illuminate\Validation\ValidationException::class);
            app(PaymentService::class)->submit($other, $invoice, ['amount' => 100]);
        });
    }

    public function test_customer_with_no_overdue_invoice_is_not_suspended(): void
    {
        $customer = $this->makeCustomer([
            'next_suspension_date' => now()->subDay()->toDateString(),
        ]);

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldNotReceive('suspendCustomer');

        $result = bind_isp($this->isp->id, fn () => (new SuspensionService($mikrotik))->suspendIfUnpaid($customer));

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('no overdue invoice', $result['message']);
    }

    public function test_overdue_customer_is_suspended_when_the_router_confirms(): void
    {
        $customer = $this->makeCustomer([
            'next_suspension_date' => now()->subDay()->toDateString(),
        ]);

        bind_isp($this->isp->id, function () use ($customer) {
            app(InvoiceService::class)->generateForCustomer($customer);
            // Force the invoice overdue and the suspension date into the past.
            $customer->invoices()->update(['due_date' => now()->subDays(5)->toDateString()]);
            $customer->forceFill(['next_suspension_date' => now()->subDay()->toDateString()])->save();
        });

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldReceive('suspendCustomer')->once()->andReturn([
            'ok' => true, 'message' => 'disabled', 'verified' => true,
        ]);

        $result = bind_isp($this->isp->id, fn () => (new SuspensionService($mikrotik))->suspendIfUnpaid($customer->fresh()));

        $this->assertSame('suspended', $result['status']);
        $this->assertSame('suspended', $customer->fresh()->internet_status);
        $this->assertTrue((bool) $customer->fresh()->suspension_owned_by_billing);
    }

    public function test_failed_router_operation_does_not_mark_the_customer_suspended(): void
    {
        $customer = $this->makeCustomer([
            'next_suspension_date' => now()->subDay()->toDateString(),
        ]);

        bind_isp($this->isp->id, function () use ($customer) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $customer->invoices()->update(['due_date' => now()->subDays(5)->toDateString()]);
            $customer->forceFill(['next_suspension_date' => now()->subDay()->toDateString()])->save();
        });

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldReceive('suspendCustomer')->once()->andReturn([
            'ok' => false, 'message' => 'router unreachable', 'verified' => false,
        ]);

        $result = bind_isp($this->isp->id, fn () => (new SuspensionService($mikrotik))->suspendIfUnpaid($customer->fresh()));

        $this->assertSame('failed', $result['status']);
        $this->assertNotSame('suspended', $customer->fresh()->internet_status,
            'A customer must never be marked suspended unless the router confirmed it');
    }

    public function test_manually_disabled_account_is_not_restored_by_billing(): void
    {
        // Suspended outside billing: ownership flag is false.
        $customer = $this->makeCustomer([
            'internet_status' => 'suspended',
            'suspension_owned_by_billing' => false,
        ]);

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldNotReceive('restoreCustomer');

        $result = bind_isp($this->isp->id, fn () => (new SuspensionService($mikrotik))->restoreIfEligible($customer));

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('suspension not owned by billing', $result['message']);
    }

    public function test_billing_owned_suspension_is_restored_after_payment(): void
    {
        $customer = $this->makeCustomer([
            'internet_status' => 'suspended',
            'suspension_owned_by_billing' => true,
        ]);

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldReceive('restoreCustomer')->once()->andReturn([
            'ok' => true, 'message' => 'enabled', 'verified' => true,
        ]);

        $result = bind_isp($this->isp->id, fn () => (new SuspensionService($mikrotik))->restoreIfEligible($customer));

        $this->assertSame('restored', $result['status']);
        $this->assertSame('active', $customer->fresh()->internet_status);
        $this->assertFalse((bool) $customer->fresh()->suspension_owned_by_billing);
    }

    public function test_customer_with_billing_disabled_is_never_suspended(): void
    {
        $customer = $this->makeCustomer([
            'billing_enabled' => false,
            'next_suspension_date' => now()->subDay()->toDateString(),
        ]);

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldNotReceive('suspendCustomer');

        $result = bind_isp($this->isp->id, fn () => (new SuspensionService($mikrotik))->suspendIfUnpaid($customer));

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('billing disabled', $result['message']);
    }

    public function test_a_ppp_account_cannot_be_assigned_to_two_customers(): void
    {
        $customer = $this->makeCustomer();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Customer::create([
            'isp_id' => $this->isp->id,
            'customer_code' => 'CDUP',
            'name' => 'Second Customer',
            'username' => 'second',
            'password' => 'secret123',
            'router_id' => $this->router->id,
            'ppp_account_id' => $customer->ppp_account_id,
            'ppp_username' => $customer->ppp_username,
            'monthly_bill_amount' => 500,
            'billing_start_date' => now()->toDateString(),
            'bill_generation_day' => 1,
        ]);
    }

    public function test_dashboard_totals_match_the_database(): void
    {
        $customer = $this->makeCustomer();
        $admin = User::create([
            'isp_id' => $this->isp->id, 'name' => 'Admin', 'username' => 'admin3',
            'email' => 'admin3@test.local', 'password' => 'password123', 'role' => 'isp_admin',
        ]);

        bind_isp($this->isp->id, function () use ($customer, $admin) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $invoice = $customer->invoices()->firstOrFail();

            $service = app(PaymentService::class);
            $submission = $service->submit($customer, $invoice, ['amount' => 300, 'reference' => 'TXN-DASH-1']);
            $service->approve($submission, $admin);
        });

        $response = $this->actingAs($admin, 'isp')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalCollected', fn ($v) => abs($v - 300.0) < 0.01);
        $response->assertViewHas('totalOutstanding', fn ($v) => abs($v - 200.0) < 0.01);
        $response->assertViewHas('pendingApprovals', 0);
    }

    public function test_suspension_honours_the_configured_local_time_of_day(): void
    {
        // Suspension is scheduled for 23:59 today in the ISP timezone. Run the
        // check at a moment before that time; it must not suspend yet.
        $customer = $this->makeCustomer([
            'next_suspension_date' => now()->toDateString(),
            'suspension_time' => '23:59:00',
        ]);

        bind_isp($this->isp->id, function () use ($customer) {
            app(InvoiceService::class)->generateForCustomer($customer);
            $customer->invoices()->update(['due_date' => now()->subDays(5)->toDateString()]);
            $customer->forceFill([
                'next_suspension_date' => now()->toDateString(),
                'suspension_time' => '23:59:00',
            ])->save();
        });

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldNotReceive('suspendCustomer');

        $service = new SuspensionService($mikrotik);

        // Unless "now" is already past 23:59 local, the run must skip.
        $dueAt = bind_isp($this->isp->id, fn () => $service->suspensionDueAt($customer->fresh()));

        $this->assertNotNull($dueAt);

        if ($dueAt->isFuture()) {
            $result = bind_isp($this->isp->id, fn () => $service->suspendIfUnpaid($customer->fresh()));
            $this->assertSame('skipped', $result['status']);
            $this->assertSame('suspension time not reached', $result['message']);
        } else {
            $this->assertTrue(true, 'Test ran after the configured suspension time.');
        }
    }

    public function test_suspension_due_time_uses_the_isp_timezone(): void
    {
        $customer = $this->makeCustomer([
            'next_suspension_date' => '2026-03-15',
            'suspension_time' => '00:00:00',
        ]);

        $service = new SuspensionService(Mockery::mock(MikrotikService::class));

        // Asia/Dhaka is UTC+6, so local midnight on 15 March is 18:00 UTC on the 14th.
        $dueAt = bind_isp($this->isp->id, fn () => $service->suspensionDueAt($customer->fresh()));

        $this->assertSame('2026-03-14 18:00:00', $dueAt->utc()->format('Y-m-d H:i:s'));
    }
}
