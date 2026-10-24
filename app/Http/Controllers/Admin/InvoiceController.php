<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()->with('customer:id,name,customer_code');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('ppp_username', 'like', "%{$search}%"));
            });
        }

        if ($customerId = $request->integer('customer')) {
            $query->where('customer_id', $customerId);
        }

        if ($from = $request->date('from')) {
            $query->whereDate('issue_date', '>=', $from->toDateString());
        }
        if ($to = $request->date('to')) {
            $query->whereDate('issue_date', '<=', $to->toDateString());
        }

        match ($request->string('status')->toString()) {
            'paid' => $query->where('status', Invoice::STATUS_PAID),
            'unpaid' => $query->where('status', Invoice::STATUS_UNPAID),
            'partial' => $query->where('status', Invoice::STATUS_PARTIAL),
            'cancelled' => $query->where('status', Invoice::STATUS_CANCELLED),
            'overdue' => $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
                ->whereDate('due_date', '<', now()->toDateString()),
            default => null,
        };

        $invoices = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $totals = [
            'invoiced' => (float) Invoice::where('status', '!=', Invoice::STATUS_CANCELLED)->sum('amount'),
            'collected' => (float) Payment::sum('amount'),
            'unpaid' => (float) Invoice::whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])->sum('balance'),
            'overdue' => (float) Invoice::whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
                ->whereDate('due_date', '<', now()->toDateString())->sum('balance'),
        ];

        $customers = Customer::orderBy('name')->get(['id', 'name', 'customer_code']);

        return view('admin.invoices.index', compact('invoices', 'totals', 'customers'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer.isp', 'items', 'payments.recordedBy', 'submissions.method']);

        return view('admin.invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer.isp', 'items', 'payments']);

        return view('admin.invoices.print', compact('invoice'));
    }

    /** Runs invoice generation on demand. */
    public function generate(Request $request, InvoiceService $service)
    {
        $result = $service->generateDueInvoices(current_isp_id());

        $message = sprintf(
            'Invoice run finished: %d created, %d skipped, %d failed.',
            $result['created'], $result['skipped'], $result['failed']
        );

        return back()->with($result['failed'] > 0 ? 'warning' : 'success', $message);
    }

    /** Records an offline payment against an invoice. */
    public function recordPayment(Request $request, Invoice $invoice, PaymentService $service)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:128'],
            'sender_number' => ['nullable', 'string', 'max:40'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['amount' => 'This invoice is cancelled.']);
        }
        if ((float) $invoice->balance <= 0) {
            throw ValidationException::withMessages(['amount' => 'This invoice is already fully paid.']);
        }

        if (! empty($data['payment_method_id'])) {
            $data['method_name'] = PaymentMethod::find($data['payment_method_id'])?->name;
        }

        $payment = $service->recordManual($invoice, $request->user('isp'), $data);

        return back()->with('success', 'Payment of '.number_format((float) $payment->amount, 2).' recorded.');
    }

    /** Cancels an invoice with a mandatory reason. */
    public function cancel(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'cancel_reason' => 'This invoice already has recorded payments and cannot be cancelled.',
            ]);
        }

        DB::transaction(function () use ($invoice, $data) {
            $invoice->forceFill([
                'status' => Invoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $data['cancel_reason'],
            ])->save();

            \App\Models\AuditLog::record('invoice.cancelled', $invoice, "Cancelled invoice {$invoice->invoice_number}: {$data['cancel_reason']}");
        });

        return back()->with('success', 'Invoice cancelled.');
    }

    /** Applies an authorised adjustment to the invoice amount. */
    public function adjust(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'amount' => 'An invoice with recorded payments cannot be adjusted. Record a credit instead.',
            ]);
        }

        $old = (float) $invoice->amount;

        DB::transaction(function () use ($invoice, $data, $old) {
            $invoice->forceFill([
                'amount' => $data['amount'],
                'balance' => $data['amount'],
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').'Adjustment: '.$data['reason']),
            ])->save();

            $invoice->items()->delete();
            $invoice->items()->create([
                'description' => 'Internet service (adjusted) - '.$invoice->billing_period_start->format('F Y'),
                'quantity' => 1,
                'unit_price' => $data['amount'],
                'amount' => $data['amount'],
            ]);

            \App\Models\AuditLog::record(
                'invoice.adjusted',
                $invoice,
                "Adjusted invoice {$invoice->invoice_number}: {$data['reason']}",
                ['amount' => $old],
                ['amount' => (float) $data['amount']]
            );
        });

        return back()->with('success', 'Invoice adjusted.');
    }
}
