<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.payment-methods.index', compact('methods'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $method = PaymentMethod::create($data + [
            'isp_id' => current_isp_id(),
            'is_enabled' => $request->boolean('is_enabled', true),
            'requires_reference' => $request->boolean('requires_reference', true),
            'requires_sender' => $request->boolean('requires_sender', true),
        ]);

        \App\Models\AuditLog::record('payment_method.created', $method, "Added payment method {$method->name}");

        return back()->with('success', 'Payment method added.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $this->validated($request, $paymentMethod);

        $paymentMethod->update($data + [
            'is_enabled' => $request->boolean('is_enabled'),
            'requires_reference' => $request->boolean('requires_reference'),
            'requires_sender' => $request->boolean('requires_sender'),
        ]);

        \App\Models\AuditLog::record('payment_method.updated', $paymentMethod, "Updated payment method {$paymentMethod->name}");

        return back()->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->submissions()->exists()) {
            $paymentMethod->update(['is_enabled' => false]);

            return back()->with('warning', 'This method has submissions, so it was disabled instead of deleted.');
        }

        \App\Models\AuditLog::record('payment_method.deleted', $paymentMethod, "Deleted payment method {$paymentMethod->name}");
        $paymentMethod->delete();

        return back()->with('success', 'Payment method removed.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?PaymentMethod $method = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['bkash', 'nagad', 'rocket', 'bank', 'cash', 'custom'])],
            'account_number' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
