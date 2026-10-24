<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'billing:mark-overdue';

    protected $description = 'Flag unpaid invoices past their due date and notify the ISP';

    public function handle(): int
    {
        $invoices = Invoice::withoutGlobalScope('isp')
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
            ->whereDate('due_date', '<', now()->toDateString())
            ->with('customer')
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->customer) {
                continue;
            }

            bind_isp($invoice->isp_id, function () use ($invoice, &$count) {
                AppNotification::notifyAdmin(
                    $invoice->isp_id,
                    'invoice_overdue',
                    'Invoice overdue',
                    "Invoice {$invoice->invoice_number} for {$invoice->customer->name} is overdue by ".$invoice->due_date->diffInDays(now()).' day(s).',
                    ['invoice_id' => $invoice->id],
                    'warning'
                );

                AppNotification::notifyCustomer(
                    $invoice->isp_id,
                    $invoice->customer_id,
                    'invoice_overdue',
                    'Invoice overdue',
                    "Invoice {$invoice->invoice_number} is overdue. Please pay ".isp_money($invoice->balance).' to avoid suspension.',
                    ['invoice_id' => $invoice->id],
                    'warning'
                );

                $count++;
            });
        }

        $this->info("Marked {$count} overdue invoice(s).");

        return self::SUCCESS;
    }
}
