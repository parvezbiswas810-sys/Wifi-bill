<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IspProfile;
use App\Models\MikrotikRouter;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\PppAccount;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises the real HTTP endpoints for authentication and the payment cycle.
 */
class HttpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected IspProfile $isp;

    protected User $admin;

    protected Customer $customer;

    protected MikrotikRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isp = IspProfile::create([
            'name' => 'HTTP ISP', 'slug' => 'http-isp', 'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT', 'invoice_prefix' => 'INV',
        ]);
        BillingSetting::create(['isp_id' => $this->isp->id, 'default_bill_amount' => 500]);

        $this->admin = User::create([
            'isp_id' => $this->isp->id, 'name' => 'Admin', 'username' => 'httpadmin',
            'email' => 'httpadmin@test.local', 'password' => 'password123', 'role' => 'isp_admin',
            'is_active' => true,
        ]);

        $this->router = MikrotikRouter::create([
            'isp_id' => $this->isp->id, 'name' => 'R', 'host' => '192.0.2.10',
            'api_port' => 8728, 'api_username' => 'api', 'api_password' => 'x',
        ]);
        $ppp = PppAccount::create([
            'isp_id' => $this->isp->id, 'router_id' => $this->router->id, 'username' => 'httpuser',
        ]);

        $this->customer = Customer::create([
            'isp_id' => $this->isp->id, 'customer_code' => 'CHTTP', 'name' => 'HTTP Customer',
            'username' => 'httpcust', 'password' => 'custpass123', 'phone' => '01700000001',
            'router_id' => $this->router->id, 'ppp_account_id' => $ppp->id, 'ppp_username' => $ppp->username,
            'monthly_bill_amount' => 500, 'billing_enabled' => true,
            'billing_start_date' => now()->toDateString(), 'bill_generation_day' => 1,
            'next_bill_date' => now()->subDay()->toDateString(),
        ]);

        PaymentMethod::create([
            'isp_id' => $this->isp->id, 'name' => 'bKash', 'type' => 'bkash',
            'account_number' => '01700000000', 'is_enabled' => true,
            'requires_reference' => true, 'requires_sender' => true,
        ]);
    }

    protected function invoice(): Invoice
    {
        bind_isp($this->isp->id, function () {
            app(InvoiceService::class)->generateForCustomer($this->customer);
        });

        return $this->customer->invoices()->firstOrFail();
    }

    protected function submitPayment(Invoice $invoice, array $overrides = [])
    {
        return $this->actingAs($this->customer, 'customer')
            ->post(route('customer.payments.store'), array_merge([
                'invoice_id' => $invoice->id,
                'payment_method_id' => PaymentMethod::first()->id,
                'amount' => 500,
                'sender_number' => '01811111111',
                'reference' => 'TXN-'.uniqid(),
            ], $overrides));
    }

    // ---------------------------------------------------------------- auth

    public function test_root_shows_the_customer_login_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Customer sign in', false);
    }

    public function test_loginisp_shows_the_administrator_login_page(): void
    {
        $this->get('/loginisp')->assertOk()->assertSee('Administrator sign in', false);
    }

    public function test_isp_administrator_can_sign_in_and_reach_the_dashboard(): void
    {
        $this->post('/loginisp', ['username' => 'httpadmin', 'password' => 'password123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin, 'isp');
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        $this->post('/loginisp', ['username' => 'httpadmin', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest('isp');
    }

    public function test_customer_can_sign_in_and_reach_the_portal(): void
    {
        $this->post('/', ['username' => 'httpcust', 'password' => 'custpass123'])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($this->customer, 'customer');
        $this->get(route('customer.dashboard'))->assertOk();
    }

    /**
     * Resolving an authenticated Customer from a cold guard must not recurse.
     *
     * The isp global scope calls current_isp_id(), which asks the auth guard for
     * the user, which loads Customer, which applies the scope again. Laravel
     * caches the resolved user in memory, so actingAs() hides the cycle: the
     * first request of a real session is the one that hangs. Reloading the user
     * between requests reproduces that cold start.
     */
    public function test_customer_portal_survives_a_cold_guard_resolution(): void
    {
        $this->post('/', ['username' => 'httpcust', 'password' => 'custpass123'])
            ->assertRedirect(route('customer.dashboard'));

        foreach (['customer.dashboard', 'customer.invoices.index', 'customer.connection', 'customer.profile'] as $name) {
            auth()->guard('customer')->forgetUser();
            app()->forgetInstance('current_isp_id');

            $this->get(route($name))->assertOk();
        }
    }

    public function test_admin_routes_reject_guests(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
        $this->get(route('admin.customers.index'))->assertRedirect();
    }

    public function test_customer_routes_reject_guests(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect();
        $this->get(route('customer.invoices.index'))->assertRedirect();
    }

    public function test_customer_cannot_reach_admin_routes(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_administrator_cannot_reach_customer_routes(): void
    {
        $this->actingAs($this->admin, 'isp')
            ->get(route('customer.dashboard'))->assertRedirect();
    }

    // ------------------------------------------------------------- payments

    public function test_customer_can_submit_a_payment_through_the_web_form(): void
    {
        $invoice = $this->invoice();

        $this->submitPayment($invoice, ['reference' => 'WEB-TXN-1'])->assertRedirect();

        $submission = PaymentSubmission::withoutGlobalScope('isp')->firstOrFail();
        $this->assertSame('pending', $submission->status);
        $this->assertSame(500.0, (float) $submission->amount);

        // Pending money is not collected money, and the invoice is untouched.
        $this->assertSame(0.0, (float) $this->customer->payments()->sum('amount'));
        $this->assertSame(500.0, (float) $invoice->fresh()->balance);
    }

    public function test_payment_submission_requires_a_reference_when_the_method_demands_one(): void
    {
        $invoice = $this->invoice();

        $this->submitPayment($invoice, ['reference' => null])
            ->assertSessionHasErrors('reference');

        $this->assertSame(0, PaymentSubmission::withoutGlobalScope('isp')->count());
    }

    public function test_payment_submission_rejects_an_amount_above_the_balance(): void
    {
        $invoice = $this->invoice();

        $this->submitPayment($invoice, ['amount' => 9999])
            ->assertSessionHasErrors('amount');
    }

    public function test_duplicate_reference_is_rejected_through_the_web_form(): void
    {
        $invoice = $this->invoice();

        $this->submitPayment($invoice, ['amount' => 200, 'reference' => 'SAME-REF']);
        $this->submitPayment($invoice, ['amount' => 200, 'reference' => 'SAME-REF'])
            ->assertSessionHasErrors();

        $this->assertSame(1, PaymentSubmission::withoutGlobalScope('isp')->count());
    }

    public function test_admin_can_approve_a_payment_and_the_invoice_is_settled(): void
    {
        $invoice = $this->invoice();
        $this->submitPayment($invoice, ['reference' => 'APPROVE-WEB-1']);

        $submission = PaymentSubmission::withoutGlobalScope('isp')->firstOrFail();

        $this->actingAs($this->admin, 'isp')
            ->post(route('admin.payments.approve', $submission), ['amount' => 500])
            ->assertRedirect();

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->balance);
        $this->assertSame(500.0, (float) $this->customer->payments()->sum('amount'));
    }

    public function test_repeated_approval_requests_record_only_one_payment(): void
    {
        $invoice = $this->invoice();
        $this->submitPayment($invoice, ['reference' => 'DOUBLE-CLICK-1']);

        $submission = PaymentSubmission::withoutGlobalScope('isp')->firstOrFail();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin, 'isp')
                ->post(route('admin.payments.approve', $submission->fresh()), ['amount' => 500]);
        }

        $this->assertSame(1, $this->customer->payments()->count());
        $this->assertSame(500.0, (float) $this->customer->payments()->sum('amount'));
        $this->assertSame(0.0, (float) $invoice->fresh()->balance);
    }

    public function test_rejecting_a_payment_requires_a_reason(): void
    {
        $invoice = $this->invoice();
        $this->submitPayment($invoice, ['reference' => 'REJECT-ME-1']);

        $submission = PaymentSubmission::withoutGlobalScope('isp')->firstOrFail();

        $this->actingAs($this->admin, 'isp')
            ->post(route('admin.payments.reject', $submission), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending', $submission->fresh()->status);

        $this->actingAs($this->admin, 'isp')
            ->post(route('admin.payments.reject', $submission), ['rejection_reason' => 'Not found in our statement'])
            ->assertRedirect();

        $this->assertSame('rejected', $submission->fresh()->status);
        $this->assertSame(500.0, (float) $invoice->fresh()->balance);
    }

    public function test_customer_cannot_submit_a_payment_against_another_customers_invoice(): void
    {
        $other = Customer::create([
            'isp_id' => $this->isp->id, 'customer_code' => 'COTHER', 'name' => 'Other',
            'username' => 'othercust', 'password' => 'otherpass123',
            'router_id' => $this->router->id, 'ppp_account_id' => null,
            'monthly_bill_amount' => 500, 'billing_start_date' => now()->toDateString(),
            'bill_generation_day' => 1, 'next_bill_date' => now()->subDay()->toDateString(),
        ]);

        bind_isp($this->isp->id, function () use ($other) {
            app(InvoiceService::class)->generateForCustomer($other);
        });
        $otherInvoice = $other->invoices()->firstOrFail();

        $this->submitPayment($otherInvoice, ['reference' => 'IDOR-1'])
            ->assertSessionHasErrors('invoice_id');

        $this->assertSame(0, PaymentSubmission::withoutGlobalScope('isp')->count());
    }

    public function test_payment_screenshot_is_stored_outside_the_public_root(): void
    {
        Storage::fake('local');
        $invoice = $this->invoice();

        $this->submitPayment($invoice, [
            'reference' => 'SHOT-1',
            'screenshot' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $submission = PaymentSubmission::withoutGlobalScope('isp')->firstOrFail();
        $this->assertNotNull($submission->screenshot_path);
        $this->assertStringStartsWith('payment-proofs/', $submission->screenshot_path);
        Storage::disk('local')->assertExists($submission->screenshot_path);
    }

    // ------------------------------------------------------------ customers

    public function test_admin_can_create_a_customer_against_an_existing_ppp_account(): void
    {
        $ppp = PppAccount::create([
            'isp_id' => $this->isp->id, 'router_id' => $this->router->id, 'username' => 'freshppp',
            'profile' => '10Mbps',
        ]);

        $this->actingAs($this->admin, 'isp')
            ->post(route('admin.customers.store'), [
                'name' => 'New Customer',
                'phone' => '01799999999',
                'username' => 'newcust',
                'password' => 'newpass123',
                'monthly_bill_amount' => 600,
                'billing_start_date' => now()->toDateString(),
                'bill_generation_day' => 5,
                'bill_generation_time' => '06:00',
                'due_days' => 7,
                'grace_period_days' => 2,
                'router_id' => $this->router->id,
                'ppp_account_id' => $ppp->id,
                'billing_enabled' => 1,
                'auto_suspend' => 1,
            ])
            ->assertRedirect();

        $created = Customer::withoutGlobalScope('isp')->where('username', 'newcust')->firstOrFail();
        $this->assertSame($ppp->id, $created->ppp_account_id);
        $this->assertSame('freshppp', $created->ppp_username);
        $this->assertSame($this->isp->id, $created->isp_id);
    }

    public function test_assigning_an_already_used_ppp_account_is_rejected(): void
    {
        $this->actingAs($this->admin, 'isp')
            ->post(route('admin.customers.store'), [
                'name' => 'Duplicate PPP',
                'username' => 'dupcust',
                'password' => 'newpass123',
                'monthly_bill_amount' => 600,
                'billing_start_date' => now()->toDateString(),
                'bill_generation_day' => 5,
                'due_days' => 7,
                'grace_period_days' => 2,
                'router_id' => $this->router->id,
                // Already assigned to the customer created in setUp().
                'ppp_account_id' => $this->customer->ppp_account_id,
                'billing_enabled' => 1,
            ])
            ->assertSessionHasErrors('ppp_account_id');

        $this->assertSame(1, Customer::withoutGlobalScope('isp')->count());
    }

    // ------------------------------------------------------------- settings

    public function test_admin_can_save_isp_settings(): void
    {
        $this->actingAs($this->admin, 'isp')
            ->put(route('admin.settings.profile'), [
                'name' => 'Renamed ISP',
                'contact_number' => '01911111111',
                'email' => 'billing@renamed.test',
                'address' => 'Dhaka',
                'website' => 'https://renamed.test',
                'invoice_prefix' => 'REN',
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
            ])
            ->assertRedirect();

        $this->assertSame('Renamed ISP', $this->isp->fresh()->name);
        $this->assertSame('REN', $this->isp->fresh()->invoice_prefix);
    }

    public function test_admin_can_update_billing_policy(): void
    {
        $this->actingAs($this->admin, 'isp')
            ->put(route('admin.settings.billing'), [
                'default_bill_amount' => 750,
                'default_billing_cycle' => 'monthly',
                'invoice_generation_time' => '01:30',
                'default_due_days' => 12,
                'grace_period_days' => 4,
                'suspension_time' => '02:00',
                'auto_suspend_enabled' => 1,
                'auto_restore_enabled' => 1,
                'notify_on_invoice' => 1,
                'notify_before_due' => 1,
                'notify_before_due_days' => 5,
                'notify_on_suspension' => 1,
                'sms_provider' => 'none',
            ])
            ->assertRedirect();

        $settings = BillingSetting::where('isp_id', $this->isp->id)->firstOrFail();
        $this->assertSame('750.00', (string) $settings->default_bill_amount);
        $this->assertSame(12, $settings->default_due_days);
        $this->assertSame(4, $settings->grace_period_days);
    }

    // -------------------------------------------------------------- security

    public function test_router_credentials_are_encrypted_at_rest_and_never_rendered(): void
    {
        $this->router->update(['api_password' => 'super-secret-router-password']);

        $raw = DB::table('mikrotik_routers')->where('id', $this->router->id)->value('api_password');
        $this->assertNotSame('super-secret-router-password', $raw, 'The stored value must be encrypted');
        $this->assertSame('super-secret-router-password', $this->router->fresh()->api_password);

        $html = $this->actingAs($this->admin, 'isp')->get(route('admin.mikrotik.index'))->getContent();
        $this->assertStringNotContainsString('super-secret-router-password', $html);
    }
}
