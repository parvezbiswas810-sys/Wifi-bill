<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\IspProfile;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the initial ISP and its administrator.
 *
 * Credentials come from the environment so production deployments never ship
 * with a known default password.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $isp = IspProfile::firstOrCreate(
            ['slug' => 'default-isp'],
            [
                'name' => env('SEED_ISP_NAME', 'Adsxuit ISP'),
                'contact_number' => env('SEED_ISP_PHONE', ''),
                'email' => env('SEED_ISP_EMAIL', ''),
                'address' => env('SEED_ISP_ADDRESS', ''),
                'website' => env('SEED_ISP_WEBSITE', ''),
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'invoice_prefix' => 'INV',
                'is_active' => true,
            ]
        );

        BillingSetting::firstOrCreate(['isp_id' => $isp->id], [
            'default_bill_amount' => 500,
            'default_billing_cycle' => 'monthly',
            'invoice_generation_time' => '00:05:00',
            'default_due_days' => 10,
            'grace_period_days' => 3,
            'suspension_time' => '00:00:00',
            'auto_suspend_enabled' => true,
            'auto_restore_enabled' => true,
            'notify_on_invoice' => true,
            'notify_before_due' => true,
            'notify_before_due_days' => 3,
            'notify_on_suspension' => true,
            'sms_provider' => 'none',
        ]);

        $adminUsername = env('SEED_ADMIN_USERNAME', 'admin');
        $adminPassword = env('SEED_ADMIN_PASSWORD');

        if (! $adminPassword) {
            $adminPassword = bin2hex(random_bytes(6));
            $this->command?->warn("No SEED_ADMIN_PASSWORD set. Generated password: {$adminPassword}");
        }

        User::updateOrCreate(
            ['username' => $adminUsername],
            [
                'isp_id' => $isp->id,
                'name' => env('SEED_ADMIN_NAME', 'ISP Administrator'),
                'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
                'password' => $adminPassword,
                'role' => 'isp_admin',
                'is_active' => true,
            ]
        );

        // Common Bangladeshi payment methods, disabled until configured.
        $methods = [
            ['bKash', 'bkash', 1],
            ['Nagad', 'nagad', 2],
            ['Rocket', 'rocket', 3],
            ['Bank Transfer', 'bank', 4],
            ['Cash', 'cash', 5],
        ];

        foreach ($methods as [$name, $type, $order]) {
            PaymentMethod::firstOrCreate(
                ['isp_id' => $isp->id, 'name' => $name],
                [
                    'type' => $type,
                    'is_enabled' => $type === 'cash',
                    'requires_reference' => $type !== 'cash',
                    'requires_sender' => in_array($type, ['bkash', 'nagad', 'rocket'], true),
                    'sort_order' => $order,
                    'instructions' => $type === 'cash'
                        ? 'Pay in person at the ISP office.'
                        : 'Send money to the number configured by your ISP, then submit the transaction ID.',
                ]
            );
        }

        $this->command?->info('Seed complete. Sign in at /loginisp with username: '.$adminUsername);
    }
}
