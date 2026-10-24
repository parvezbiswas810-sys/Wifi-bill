<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IspProfile;
use App\Models\MikrotikRouter;
use App\Models\PppAccount;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves that one ISP can never read or change another ISP's data.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected IspProfile $ispA;

    protected IspProfile $ispB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['a', 'b'] as $key) {
            $isp = IspProfile::create([
                'name' => 'ISP '.strtoupper($key),
                'slug' => 'isp-'.$key,
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'invoice_prefix' => strtoupper($key),
            ]);
            BillingSetting::create(['isp_id' => $isp->id, 'default_bill_amount' => 500]);
            $this->{'isp'.strtoupper($key)} = $isp;
        }
    }

    protected function makeCustomer(IspProfile $isp, string $suffix): Customer
    {
        $router = MikrotikRouter::create([
            'isp_id' => $isp->id, 'name' => 'R'.$suffix, 'host' => '192.0.2.'.rand(1, 250),
            'api_port' => 8728, 'api_username' => 'api', 'api_password' => 'x',
        ]);

        $ppp = PppAccount::create([
            'isp_id' => $isp->id, 'router_id' => $router->id, 'username' => 'ppp'.$suffix,
        ]);

        return Customer::create([
            'isp_id' => $isp->id,
            'customer_code' => 'C'.$suffix,
            'name' => 'Customer '.$suffix,
            'username' => 'user'.$suffix,
            'password' => 'secret123',
            'router_id' => $router->id,
            'ppp_account_id' => $ppp->id,
            'ppp_username' => $ppp->username,
            'monthly_bill_amount' => 500,
            'billing_start_date' => now()->toDateString(),
            'bill_generation_day' => 1,
            'next_bill_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_customer_queries_are_scoped_to_the_active_isp(): void
    {
        $customerA = $this->makeCustomer($this->ispA, 'a1');
        $customerB = $this->makeCustomer($this->ispB, 'b1');

        $visibleToA = bind_isp($this->ispA->id, fn () => Customer::pluck('id')->all());
        $visibleToB = bind_isp($this->ispB->id, fn () => Customer::pluck('id')->all());

        $this->assertContains($customerA->id, $visibleToA);
        $this->assertNotContains($customerB->id, $visibleToA);
        $this->assertContains($customerB->id, $visibleToB);
        $this->assertNotContains($customerA->id, $visibleToB);
    }

    public function test_admin_cannot_view_another_isps_customer(): void
    {
        $customerB = $this->makeCustomer($this->ispB, 'b2');

        $adminA = User::create([
            'isp_id' => $this->ispA->id, 'name' => 'Admin A', 'username' => 'admina',
            'email' => 'a@test.local', 'password' => 'password123', 'role' => 'isp_admin',
        ]);

        $this->actingAs($adminA, 'isp')
            ->get(route('admin.customers.show', $customerB))
            ->assertNotFound();
    }

    public function test_admin_cannot_view_another_isps_invoice(): void
    {
        $customerB = $this->makeCustomer($this->ispB, 'b3');

        bind_isp($this->ispB->id, function () use ($customerB) {
            app(InvoiceService::class)->generateForCustomer($customerB);
        });

        $invoiceB = Invoice::withoutGlobalScope('isp')->where('customer_id', $customerB->id)->firstOrFail();

        $adminA = User::create([
            'isp_id' => $this->ispA->id, 'name' => 'Admin A', 'username' => 'admina2',
            'email' => 'a2@test.local', 'password' => 'password123', 'role' => 'isp_admin',
        ]);

        $this->actingAs($adminA, 'isp')
            ->get(route('admin.invoices.show', $invoiceB))
            ->assertNotFound();
    }

    public function test_new_records_are_automatically_stamped_with_the_active_isp(): void
    {
        $created = bind_isp($this->ispA->id, function () {
            return \App\Models\PaymentMethod::create(['name' => 'bKash A', 'type' => 'bkash']);
        });

        $this->assertSame($this->ispA->id, $created->isp_id);
    }

    public function test_customer_cannot_view_another_customers_invoice(): void
    {
        $customerA = $this->makeCustomer($this->ispA, 'a2');
        $customerB = $this->makeCustomer($this->ispA, 'a3');

        bind_isp($this->ispA->id, function () use ($customerB) {
            app(InvoiceService::class)->generateForCustomer($customerB);
        });

        $invoiceB = Invoice::withoutGlobalScope('isp')->where('customer_id', $customerB->id)->firstOrFail();

        // Customer A is signed in and guesses customer B's invoice id.
        $this->actingAs($customerA, 'customer')
            ->get(route('customer.invoices.show', $invoiceB))
            ->assertNotFound();
    }
}
