<?php

namespace App\Console\Commands;

use App\Models\IspProfile;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate-invoices {--isp= : Limit to a single ISP id}';

    protected $description = 'Generate invoices for customers whose billing date has arrived';

    public function handle(InvoiceService $service): int
    {
        $ispIds = $this->option('isp')
            ? [(int) $this->option('isp')]
            : IspProfile::where('is_active', true)->pluck('id')->all();

        $totals = ['created' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($ispIds as $ispId) {
            bind_isp($ispId, function () use ($ispId, $service, &$totals) {
                $result = $service->generateDueInvoices($ispId);
                foreach (['created', 'skipped', 'failed'] as $key) {
                    $totals[$key] += $result[$key];
                }
                foreach ($result['details'] as $line) {
                    $this->line("  [isp {$ispId}] {$line}");
                }
            });
        }

        $this->info(sprintf(
            'Invoice generation complete: %d created, %d skipped, %d failed',
            $totals['created'], $totals['skipped'], $totals['failed']
        ));

        return self::SUCCESS;
    }
}
