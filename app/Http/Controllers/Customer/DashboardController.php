<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function index()
    {
        $customer = auth('customer')->user();
        $customer->load(['router:id,name,last_status,last_synced_at', 'pppAccount']);

        $latestInvoice = $customer->latestInvoice();
        $lastPayment = $customer->payments()->orderByDesc('paid_at')->first();
        $pendingSubmission = $customer->submissions()
            ->where('status', 'pending')->orderByDesc('id')->first();

        $stats = [
            'outstanding' => $customer->outstandingBalance(),
            'days_to_bill' => $customer->daysUntilNextBill(),
            'days_to_suspension' => $customer->daysUntilSuspension(),
            'invoiced_total' => (float) $customer->invoices()
                ->where('status', '!=', Invoice::STATUS_CANCELLED)->sum('amount'),
            'paid_total' => (float) $customer->payments()->sum('amount'),
            'invoice_count' => $customer->invoices()->count(),
        ];

        $recentInvoices = $customer->invoices()->orderByDesc('id')->limit(5)->get();
        $notifications = $customer->notifications()->orderByDesc('id')->limit(5)->get();

        return view('customer.dashboard', compact(
            'customer', 'latestInvoice', 'lastPayment', 'pendingSubmission', 'stats',
            'recentInvoices', 'notifications'
        ));
    }
}
