<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;

/**
 * Customer invoice history.
 *
 * Ownership is verified on every action, so changing an id in the URL cannot
 * expose another customer's invoice.
 */
class InvoiceController extends Controller
{
    public function index()
    {
        $customer = auth('customer')->user();

        $invoices = $customer->invoices()
            ->orderByDesc('id')->paginate(15);

        $totals = [
            'invoiced' => (float) $customer->invoices()->where('status', '!=', Invoice::STATUS_CANCELLED)->sum('amount'),
            'paid' => (float) $customer->payments()->sum('amount'),
            'outstanding' => $customer->outstandingBalance(),
        ];

        return view('customer.invoices.index', compact('customer', 'invoices', 'totals'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorizeOwnership($invoice);
        $invoice->load(['items', 'customer.isp', 'payments']);

        return view('customer.invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $this->authorizeOwnership($invoice);
        $invoice->load(['items', 'customer.isp', 'payments']);

        return view('customer.invoices.print', compact('invoice'));
    }

    protected function authorizeOwnership(Invoice $invoice): void
    {
        abort_unless($invoice->customer_id === auth('customer')->id(), 404);
    }
}
