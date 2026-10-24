<?php

namespace App\Console\Commands;

use App\Models\IspProfile;
use App\Services\SuspensionService;
use Illuminate\Console\Command;

class SuspendDueCustomers extends Command
{
    protected $signature = 'billing:suspend-due {--isp= : Limit to a single ISP id}';

    protected $description = 'Suspend internet for customers whose suspension date has arrived';

    public function handle(SuspensionService $service): int
    {
        $ispIds = $this->option('isp')
            ? [(int) $this->option('isp')]
            : IspProfile::where('is_active', true)->pluck('id')->all();

        $totals = ['suspended' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($ispIds as $ispId) {
            bind_isp($ispId, function () use ($ispId, $service, &$totals) {
                $result = $service->suspendDueCustomers($ispId);
                foreach (['suspended', 'skipped', 'failed'] as $key) {
                    $totals[$key] += $result[$key];
                }
                foreach ($result['details'] as $line) {
                    $this->line("  [isp {$ispId}] {$line}");
                }
            });
        }

        $this->info(sprintf(
            'Suspension run complete: %d suspended, %d skipped, %d failed',
            $totals['suspended'], $totals['skipped'], $totals['failed']
        ));

        return self::SUCCESS;
    }
}
