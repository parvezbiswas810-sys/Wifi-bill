<?php

namespace App\Console\Commands;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\IspProfile;
use App\Models\MikrotikRouter;
use App\Models\PaymentMethod;
use App\Models\PppAccount;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Local verification helper: seeds a demo tenant and requests every page,
 * reporting any route that fails to render. Never run in production.
 */
class SmokeTestPages extends Command
{
    protected $signature = 'app:smoke-test {--fresh : Rebuild the database first}';

    protected $description = 'Render every application page against seeded demo data';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->line(trim(Artisan::output()));
        }

        $isp = IspProfile::firstOrCreate(['slug' => 'smoke'], [
            'name' => 'Adsxuit ISP', 'timezone' => 'Asia/Dhaka', 'currency' => 'BDT',
            'invoice_prefix' => 'INV', 'contact_number' => '01700000000', 'email' => 'support@adsxuit.com',
        ]);
        BillingSetting::firstOrCreate(['isp_id' => $isp->id], ['default_bill_amount' => 500]);

        $admin = User::firstOrCreate(['username' => 'smokeadmin'], [
            'isp_id' => $isp->id, 'name' => 'Smoke Admin', 'email' => 'smoke@adsxuit.com',
            'password' => 'smoke12345', 'role' => 'isp_admin', 'is_active' => true,
        ]);

        $router = MikrotikRouter::firstOrCreate(['isp_id' => $isp->id, 'name' => 'Main Tower'], [
            'host' => '103.100.100.1', 'api_port' => 8728, 'api_username' => 'api',
            'api_password' => 'secret', 'is_enabled' => true,
        ]);

        $ppp = PppAccount::firstOrCreate(['router_id' => $router->id, 'username' => 'rahim01'], [
            'isp_id' => $isp->id, 'router_secret_id' => '*1', 'profile' => '10Mbps',
            'is_active_session' => true, 'session_uptime' => '2d3h',
        ]);

        $cust = Customer::firstOrCreate(['username' => 'rahim01'], [
            'isp_id' => $isp->id, 'customer_code' => 'C00001', 'name' => 'Rahim Uddin',
            'phone' => '01711111111', 'address' => 'Mirpur, Dhaka', 'password' => 'rahim123',
            'router_id' => $router->id, 'ppp_account_id' => $ppp->id, 'ppp_username' => 'rahim01',
            'monthly_bill_amount' => 500, 'billing_enabled' => true,
            'billing_start_date' => now()->startOfMonth()->toDateString(), 'bill_generation_day' => 1,
            'bill_generation_time' => '00:05:00', 'next_bill_date' => now()->subDay()->toDateString(),
            'due_days' => 10, 'grace_period_days' => 3, 'auto_suspend' => true,
        ]);

        PaymentMethod::firstOrCreate(['isp_id' => $isp->id, 'name' => 'bKash'], [
            'type' => 'bkash', 'account_number' => '01700000000', 'is_enabled' => true,
            'instructions' => 'Send money then submit the transaction ID.',
        ]);

        bind_isp($isp->id, function () use ($cust) {
            app(InvoiceService::class)->generateForCustomer($cust);
        });

        $inv = $cust->invoices()->first();

        $routes = [
            ['/', 'guest'], ['/loginisp', 'guest'],
            ['/admin/dashboard', 'admin'], ['/admin/customers', 'admin'],
            ['/admin/customers/create', 'admin'], ['/admin/customers/'.$cust->id, 'admin'],
            ['/admin/customers/'.$cust->id.'/edit', 'admin'],
            ['/admin/ppp-clients', 'admin'], ['/admin/ppp-clients?tab=all', 'admin'],
            ['/admin/ppp-clients?tab=inactive', 'admin'], ['/admin/mikrotik', 'admin'],
            ['/admin/mikrotik-logs', 'admin'], ['/admin/billing-history', 'admin'],
            ['/admin/billing-history/'.$inv->id, 'admin'],
            ['/admin/billing-history/'.$inv->id.'/print', 'admin'],
            ['/admin/payments', 'admin'], ['/admin/payment-methods', 'admin'],
            ['/admin/reports', 'admin'], ['/admin/reports?type=outstanding', 'admin'],
            ['/admin/reports?type=active_ppp', 'admin'], ['/admin/reports?type=inactive_ppp', 'admin'],
            ['/admin/reports?type=overdue_customers', 'admin'],
            ['/admin/reports?type=suspended_customers', 'admin'],
            ['/admin/reports?type=payment_approvals', 'admin'],
            ['/admin/reports?type=mikrotik_failures', 'admin'],
            ['/admin/reports?type=monthly_collection', 'admin'],
            ['/admin/reports/export?type=daily_collection', 'admin'],
            ['/admin/notifications', 'admin'], ['/admin/audit-log', 'admin'],
            ['/admin/settings', 'admin'], ['/admin/routers/'.$router->id.'/ppp-options', 'admin'],
            ['/customer/dashboard', 'customer'], ['/customer/invoices', 'customer'],
            ['/customer/invoices/'.$inv->id, 'customer'],
            ['/customer/invoices/'.$inv->id.'/print', 'customer'],
            ['/customer/payments', 'customer'], ['/customer/payments/new', 'customer'],
            ['/customer/connection', 'customer'], ['/customer/profile', 'customer'],
            ['/customer/notifications', 'customer'],
        ];

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $failures = 0;

        foreach ($routes as [$uri, $guard]) {
            $request = Request::create($uri, 'GET');

            if ($guard === 'admin') {
                auth()->guard('isp')->setUser($admin);
                app()->instance('current_isp_id', (int) $isp->id);
            } elseif ($guard === 'customer') {
                auth()->guard('customer')->setUser($cust);
                app()->instance('current_isp_id', (int) $isp->id);
            } else {
                auth()->guard('isp')->forgetUser();
                auth()->guard('customer')->forgetUser();
                app()->forgetInstance('current_isp_id');
            }

            try {
                $response = $kernel->handle($request);
                $status = $response->getStatusCode();

                if ($status === 200) {
                    $this->line("  <info>OK </info> {$status}  {$uri}");
                } else {
                    $failures++;
                    $this->line("  <error>BAD</error> {$status}  {$uri}");
                    if ($status >= 500) {
                        $text = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));
                        $this->line('       '.mb_substr($text, 0, 220));
                    }
                }
            } catch (\Throwable $e) {
                $failures++;
                $this->line("  <error>EXC</error>      {$uri}");
                $this->line('       '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info($failures === 0 ? 'All pages rendered successfully.' : "{$failures} page(s) failed.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
