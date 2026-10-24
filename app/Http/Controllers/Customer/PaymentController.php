<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /** Payment submission history. */
    public function index()
    {
        $customer = auth('customer')->user();

        $submissions = $customer->submissions()
            ->with(['invoice:id,invoice_number,balance', 'method:id,name'])
            ->orderByDesc('id')->paginate(15);

        return view('customer.payments.index', compact('customer', 'submissions'));
    }

    /** Payment form for a specific invoice. */
    public function create(Request $request)
    {
        $customer = auth('customer')->user();

        $invoices = $customer->outstandingInvoices()
            ->orderBy('due_date')->get();

        $selected = null;
        if ($invoiceId = $request->integer('invoice')) {
            $selected = $customer->invoices()->whereKey($invoiceId)->first();
        }
        $selected ??= $invoices->first();

        $methods = PaymentMethod::enabled()->get();

        return view('customer.payments.create', compact('customer', 'invoices', 'selected', 'methods'));
    }

    public function store(Request $request, PaymentService $service)
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'sender_number' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:128'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // The query is scoped to the authenticated customer's ISP, so another
        // tenant's invoice is simply not found. The explicit customer_id check
        // then blocks one customer from paying another customer's invoice.
        $invoice = Invoice::whereKey($data['invoice_id'])->first();
        if (! $invoice || (int) $invoice->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages(['invoice_id' => 'Select a valid invoice from your own account.']);
        }

        $method = PaymentMethod::whereKey($data['payment_method_id'])->where('is_enabled', true)->first();
        if (! $method) {
            throw ValidationException::withMessages(['payment_method_id' => 'Select an enabled payment method.']);
        }

        if ($method->requires_reference && blank($data['reference'])) {
            throw ValidationException::withMessages(['reference' => 'A transaction ID is required for '.$method->name.'.']);
        }
        if ($method->requires_sender && blank($data['sender_number'])) {
            throw ValidationException::withMessages(['sender_number' => 'The sender number is required for '.$method->name.'.']);
        }

        if ($request->hasFile('screenshot')) {
            // Stored outside the public web root; served only to the owning ISP.
            $data['screenshot_path'] = $request->file('screenshot')->store('payment-proofs', 'local');
        }

        $data['method_name'] = $method->name;

        $submission = $service->submit($customer, $invoice, $data);

        return redirect()->route('customer.payments.index')->with(
            'success',
            'Payment submitted. It is now pending administrator verification.'
        );
    }
}
