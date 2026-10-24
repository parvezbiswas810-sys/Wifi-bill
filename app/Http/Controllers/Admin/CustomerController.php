<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppAccount;
use App\Services\SuspensionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->with(['router:id,name', 'pppAccount:id,username,is_active_session,disabled']);

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ppp_username', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('internet_status')->toString()) {
            $query->where('internet_status', $status);
        }

        if ($request->string('billing')->toString() === 'disabled') {
            $query->where('billing_enabled', false);
        } elseif ($request->string('billing')->toString() === 'enabled') {
            $query->where('billing_enabled', true);
        }

        $customers = $query->orderByDesc('id')->paginate(20)->withQueryString();

        // Counts for the summary strip.
        $summary = [
            'total' => Customer::count(),
            'active' => Customer::where('billing_enabled', true)->where('status', 'active')->count(),
            'suspended' => Customer::where('internet_status', 'suspended')->count(),
            'archived' => Customer::where('status', 'archived')->count(),
        ];

        return view('admin.customers.index', compact('customers', 'summary'));
    }

    public function create()
    {
        return view('admin.customers.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $router = MikrotikRouter::findOrFail($data['router_id']);
        $ppp = PppAccount::where('router_id', $router->id)
            ->where('id', $data['ppp_account_id'])
            ->first();

        if (! $ppp) {
            throw ValidationException::withMessages([
                'ppp_account_id' => 'The selected PPP account does not belong to the selected router.',
            ]);
        }

        // A PPP account may back only one customer.
        if (Customer::withTrashed()->where('ppp_account_id', $ppp->id)->exists()) {
            throw ValidationException::withMessages([
                'ppp_account_id' => "PPP account \"{$ppp->username}\" is already assigned to another customer.",
            ]);
        }

        $customer = DB::transaction(function () use ($data, $router, $ppp, $request) {
            $customer = Customer::create([
                'customer_code' => $this->nextCode(),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'username' => $data['username'],
                'password' => $data['password'],
                'router_id' => $router->id,
                'ppp_account_id' => $ppp->id,
                'ppp_username' => $ppp->username,
                'monthly_bill_amount' => $data['monthly_bill_amount'],
                'billing_enabled' => $request->boolean('billing_enabled'),
                'billing_start_date' => $data['billing_start_date'],
                'bill_generation_day' => $data['bill_generation_day'],
                'bill_generation_time' => $data['bill_generation_time'] ?? '00:05:00',
                'due_days' => $data['due_days'],
                'grace_period_days' => $data['grace_period_days'],
                'auto_suspend' => $request->boolean('auto_suspend', true),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'internet_status' => $ppp->disabled ? 'suspended' : ($ppp->is_active_session ? 'active' : 'offline'),
            ]);

            // The first invoice is scheduled from the configured start date;
            // historical invoices are never back-filled.
            $customer->forceFill([
                'next_bill_date' => $this->firstBillDate($customer)->toDateString(),
            ])->save();

            return $customer;
        });

        \App\Models\AuditLog::record('customer.created', $customer, "Created customer {$customer->name} ({$customer->customer_code})", new: [
            'ppp_username' => $customer->ppp_username,
            'monthly_bill_amount' => (float) $customer->monthly_bill_amount,
        ]);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', "Customer {$customer->name} created. First invoice scheduled for {$customer->next_bill_date->format('d M Y')}.");
    }

    public function show(Customer $customer)
    {
        $customer->load(['router', 'pppAccount', 'suspensionLogs' => fn ($q) => $q->orderByDesc('id')->limit(10)]);

        $invoices = $customer->invoices()->orderByDesc('id')->limit(10)->get();
        $payments = $customer->payments()->orderByDesc('paid_at')->limit(10)->get();

        $stats = [
            'invoiced' => (float) $customer->invoices()->where('status', '!=', Invoice::STATUS_CANCELLED)->sum('amount'),
            'collected' => (float) $customer->payments()->sum('amount'),
            'outstanding' => $customer->outstandingBalance(),
        ];

        return view('admin.customers.show', compact('customer', 'invoices', 'payments', 'stats'));
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.edit', array_merge(
            $this->formData(),
            compact('customer')
        ));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request, $customer);

        $router = MikrotikRouter::findOrFail($data['router_id']);
        $ppp = PppAccount::where('router_id', $router->id)->where('id', $data['ppp_account_id'])->first();

        if (! $ppp) {
            throw ValidationException::withMessages([
                'ppp_account_id' => 'The selected PPP account does not belong to the selected router.',
            ]);
        }

        $taken = Customer::withTrashed()
            ->where('ppp_account_id', $ppp->id)
            ->whereKeyNot($customer->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'ppp_account_id' => "PPP account \"{$ppp->username}\" is already assigned to another customer.",
            ]);
        }

        $before = $customer->only(['name', 'phone', 'monthly_bill_amount', 'ppp_username', 'billing_enabled', 'status']);

        $oldPppId = $customer->ppp_account_id;
        $oldBillDay = (int) $customer->bill_generation_day;

        DB::transaction(function () use ($customer, $data, $router, $ppp, $request, $oldBillDay) {
            $customer->fill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'username' => $data['username'],
                'router_id' => $router->id,
                'ppp_account_id' => $ppp->id,
                'ppp_username' => $ppp->username,
                'monthly_bill_amount' => $data['monthly_bill_amount'],
                'billing_enabled' => $request->boolean('billing_enabled'),
                'billing_start_date' => $data['billing_start_date'],
                'bill_generation_day' => $data['bill_generation_day'],
                'bill_generation_time' => $data['bill_generation_time'] ?? '00:05:00',
                'due_days' => $data['due_days'],
                'grace_period_days' => $data['grace_period_days'],
                'auto_suspend' => $request->boolean('auto_suspend'),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($request->filled('password')) {
                $customer->password = $data['password'];
            }

            // Changing the billing day re-anchors only future invoices.
            if ((int) $data['bill_generation_day'] !== $oldBillDay) {
                $customer->next_bill_date = $this->firstBillDate($customer, true)->toDateString();
            }

            $customer->save();
        });

        \App\Models\AuditLog::record(
            'customer.updated',
            $customer,
            "Updated customer {$customer->name}",
            $before,
            $customer->only(array_keys($before))
        );

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $request->validate(['confirm_name' => ['required', 'string']]);

        if ($request->string('confirm_name')->trim()->toString() !== $customer->name) {
            throw ValidationException::withMessages([
                'confirm_name' => 'The name you typed does not match this customer.',
            ]);
        }

        $outstanding = $customer->outstandingBalance();
        if ($outstanding > 0) {
            throw ValidationException::withMessages([
                'confirm_name' => 'This customer still owes '.number_format($outstanding, 2).'. Settle or cancel the invoices first.',
            ]);
        }

        \App\Models\AuditLog::record('customer.deleted', $customer, "Archived customer {$customer->name}");

        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', 'Customer archived.');
    }

    /** Toggles billing without deleting anything. */
    public function toggleBilling(Customer $customer)
    {
        $customer->forceFill(['billing_enabled' => ! $customer->billing_enabled])->save();

        \App\Models\AuditLog::record(
            'customer.billing_toggled',
            $customer,
            'Billing '.($customer->billing_enabled ? 'enabled' : 'disabled')." for {$customer->name}"
        );

        return back()->with('success', 'Billing '.($customer->billing_enabled ? 'enabled' : 'disabled').'.');
    }

    /** Manually suspends or restores internet via the router. */
    public function toggleInternet(Request $request, Customer $customer, SuspensionService $suspension)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $suspend = $request->string('action')->toString() === 'suspend';
        $reason = $request->string('reason')->trim()->toString() ?: ($suspend ? 'manual suspension' : 'manual restoration');

        $result = $suspension->manualToggle($customer, $suspend, $reason);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? ($suspend ? 'Internet suspended and confirmed by the router.' : 'Internet restored and confirmed by the router.')
                : 'Router operation failed: '.$result['message']
        );
    }

    /** PPP accounts available for assignment on a router (AJAX). */
    public function pppOptions(MikrotikRouter $router)
    {
        $accounts = PppAccount::where('router_id', $router->id)->orderBy('username')->get();

        $assigned = Customer::withTrashed()
            ->whereIn('ppp_account_id', $accounts->pluck('id'))
            ->pluck('customer_code', 'ppp_account_id');

        $options = $accounts->map(function ($a) use ($assigned) {
            $owner = $assigned[$a->id] ?? null;
            $bits = array_filter([
                $a->profile ? 'Profile: '.$a->profile : null,
                $a->is_active_session ? 'Online' : 'Offline',
                $a->disabled ? 'Disabled on router' : null,
            ]);

            return [
                'value' => (string) $a->id,
                'label' => $a->username,
                'meta' => $owner
                    ? "Already assigned to {$owner}"
                    : implode(' · ', $bits),
                'disabled' => (bool) $owner,
            ];
        })->values();

        return response()->json([
            'router' => $router->name,
            'router_status' => $router->last_status,
            'last_synced_at' => optional($router->last_synced_at)->diffForHumans(),
            'options' => $options,
            'total' => $accounts->count(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [
            'routers' => MikrotikRouter::where('is_enabled', true)->orderBy('name')->get(),
            'settings' => BillingSetting::firstOrCreate(['isp_id' => current_isp_id()], []),
        ];
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'username' => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('customers', 'username')->ignore($customer?->id)->withoutTrashed(),
            ],
            'password' => [$customer ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
            'router_id' => ['required', Rule::exists('mikrotik_routers', 'id')->where('isp_id', current_isp_id())],
            'ppp_account_id' => ['required', Rule::exists('ppp_accounts', 'id')->where('isp_id', current_isp_id())],
            'monthly_bill_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'billing_start_date' => ['required', 'date'],
            'bill_generation_day' => ['required', 'integer', 'min:1', 'max:28'],
            'bill_generation_time' => ['nullable', 'date_format:H:i'],
            'due_days' => ['required', 'integer', 'min:0', 'max:90'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:90'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function firstBillDate(Customer $customer, bool $fromToday = false): \Carbon\Carbon
    {
        $day = (int) ($customer->bill_generation_day ?: 1);
        $start = $fromToday
            ? now()->startOfDay()
            : \Carbon\Carbon::parse($customer->billing_start_date)->startOfDay();

        $candidate = $start->copy()->day(min($day, $start->daysInMonth));

        if ($candidate->isBefore($start)) {
            $next = $start->copy()->addMonthNoOverflow();
            $candidate = $next->day(min($day, $next->daysInMonth));
        }

        return $candidate;
    }

    protected function nextCode(): string
    {
        $ispId = current_isp_id();
        $sequence = Customer::withTrashed()->where('isp_id', $ispId)->count() + 1;

        do {
            $code = 'C'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            $exists = Customer::withTrashed()->where('isp_id', $ispId)->where('customer_code', $code)->exists();
            $sequence++;
        } while ($exists);

        return $code;
    }
}
