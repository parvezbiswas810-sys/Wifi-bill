@extends('layouts.app')

@section('title', $customer->name)
@section('subtitle', $customer->customer_code.' · '.$customer->username)

@section('header-actions')
    <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-secondary btn-sm">Edit</a>
    <a href="{{ route('admin.invoices.index', ['customer' => $customer->id]) }}" class="btn btn-secondary btn-sm">
        Invoices
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {{-- ============ Left column ============ --}}
        <div class="space-y-4 xl:col-span-2">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-stat-card label="Invoiced" :value="isp_money($stats['invoiced'])" />
                <x-stat-card label="Collected" :value="isp_money($stats['collected'])" tone="success" />
                <x-stat-card label="Outstanding" :value="isp_money($stats['outstanding'])"
                             :tone="$stats['outstanding'] > 0 ? 'warning' : 'success'" />
            </div>

            <x-card title="Account details">
                <div class="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                    <dl>
                        <div class="kv"><dt>Customer code</dt><dd>{{ $customer->customer_code }}</dd></div>
                        <div class="kv"><dt>Phone</dt><dd>{{ $customer->phone ?? '-' }}</dd></div>
                        <div class="kv"><dt>Portal username</dt><dd>{{ $customer->username }}</dd></div>
                        <div class="kv"><dt>Address</dt><dd>{{ $customer->address ?? '-' }}</dd></div>
                        <div class="kv"><dt>Created</dt><dd>{{ $customer->created_at?->format('d M Y') }}</dd></div>
                        <div class="kv"><dt>Last login</dt><dd>{{ $customer->last_login_at?->diffForHumans() ?? 'never' }}</dd></div>
                    </dl>
                    <dl>
                        <div class="kv"><dt>Monthly bill</dt><dd>{{ isp_money($customer->monthly_bill_amount) }}</dd></div>
                        <div class="kv"><dt>Billing enabled</dt><dd>{{ $customer->billing_enabled ? 'Yes' : 'No' }}</dd></div>
                        <div class="kv"><dt>Bill generation</dt><dd>Day {{ $customer->bill_generation_day }} at {{ substr($customer->bill_generation_time, 0, 5) }}</dd></div>
                        <div class="kv"><dt>Due after</dt><dd>{{ $customer->due_days }} day(s)</dd></div>
                        <div class="kv"><dt>Grace period</dt><dd>{{ $customer->grace_period_days }} day(s)</dd></div>
                        <div class="kv"><dt>Auto suspend</dt><dd>{{ $customer->auto_suspend ? 'Yes' : 'No' }}</dd></div>
                    </dl>
                </div>
            </x-card>

            <x-card title="Recent invoices" :pad="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Invoice</th><th>Period</th><th>Issue</th><th>Due</th><th>Amount</th><th>Balance</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="font-medium"
                                           style="color: var(--color-brand-600)">{{ $invoice->invoice_number }}</a>
                                    </td>
                                    <td class="text-sm">{{ $invoice->billing_period_start->format('M Y') }}</td>
                                    <td class="text-sm">{{ $invoice->issue_date->format('d M Y') }}</td>
                                    <td class="text-sm">{{ $invoice->due_date->format('d M Y') }}</td>
                                    <td class="font-semibold">{{ isp_money($invoice->amount) }}</td>
                                    <td>{{ isp_money($invoice->balance) }}</td>
                                    <td><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty title="No invoices yet"
                                    message="The first invoice is generated on the configured billing date." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Payment history" :pad="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Date</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Amount</th><th>Recorded by</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="text-sm">{{ $payment->paid_at?->format('d M Y, H:i') }}</td>
                                    <td class="text-sm">{{ $payment->invoice?->invoice_number ?? '-' }}</td>
                                    <td class="text-sm">{{ $payment->method_name ?? '-' }}</td>
                                    <td class="text-sm">{{ $payment->reference ?? '-' }}</td>
                                    <td class="font-semibold">{{ isp_money($payment->amount) }}</td>
                                    <td class="text-sm">{{ $payment->recordedBy?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty title="No payments yet" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ============ Right column ============ --}}
        <div class="space-y-4">
            <x-card title="Internet status">
                @php($label = $customer->internetStatusLabel())
                <div class="flex items-center gap-3">
                    <span class="badge {{ match($label) {
                        'Active' => 'badge-success',
                        'Suspended' => 'badge-danger',
                        'Router Unavailable' => 'badge-warning',
                        default => 'badge-muted',
                    } }}">{{ $label }}</span>
                    @if ($customer->pppAccount?->is_active_session)
                        <span class="text-xs text-muted">Live session, uptime {{ $customer->pppAccount->session_uptime }}</span>
                    @endif
                </div>

                <dl class="mt-4">
                    <div class="kv"><dt>PPP username</dt><dd>{{ $customer->ppp_username ?? '-' }}</dd></div>
                    <div class="kv"><dt>Router</dt><dd>{{ $customer->router?->name ?? '-' }}</dd></div>
                    <div class="kv"><dt>Router status</dt>
                        <dd>{{ $customer->router ? ($customer->router->isOnline() ? 'Online' : 'Offline') : '-' }}</dd></div>
                    <div class="kv"><dt>Next bill date</dt><dd>{{ $customer->next_bill_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div class="kv"><dt>Next suspension</dt><dd>{{ $customer->next_suspension_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div class="kv"><dt>Last suspended</dt><dd>{{ $customer->last_suspended_at?->format('d M Y H:i') ?? 'never' }}</dd></div>
                    <div class="kv"><dt>Last restored</dt><dd>{{ $customer->last_restored_at?->format('d M Y H:i') ?? 'never' }}</dd></div>
                    <div class="kv"><dt>Suspension owner</dt>
                        <dd>{{ $customer->suspension_owned_by_billing ? 'Billing system' : 'Not billing' }}</dd></div>
                </dl>

                @if ($customer->router && ! $customer->router->isOnline())
                    <div class="alert alert-warning mt-4">
                        <span>The router is currently unreachable. The status shown may be stale
                            (last sync {{ $customer->router->last_synced_at?->diffForHumans() ?? 'never' }}).</span>
                    </div>
                @endif
            </x-card>

            <x-card title="Internet control" subtitle="Changes are applied on the router and verified">
                <form method="POST" action="{{ route('admin.customers.toggle-internet', $customer) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="action" value="{{ $customer->internet_status === 'suspended' ? 'restore' : 'suspend' }}">
                    <div>
                        <label for="reason" class="label">Reason</label>
                        <input id="reason" name="reason" type="text" class="input"
                               placeholder="{{ $customer->internet_status === 'suspended' ? 'Payment received' : 'Non-payment' }}">
                    </div>
                    <button type="submit" class="btn {{ $customer->internet_status === 'suspended' ? 'btn-success' : 'btn-warning' }} w-full">
                        {{ $customer->internet_status === 'suspended' ? 'Restore internet' : 'Suspend internet' }}
                    </button>
                </form>

                <div class="divider my-4"></div>

                <form method="POST" action="{{ route('admin.customers.toggle-billing', $customer) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full">
                        {{ $customer->billing_enabled ? 'Disable billing' : 'Enable billing' }}
                    </button>
                </form>
            </x-card>

            <x-card title="Recent suspension activity" :pad="false">
                <div class="divide-y border-app border-t">
                    @forelse ($customer->suspensionLogs as $log)
                        <div class="flex items-start gap-3 px-4 py-3">
                            <span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }} flex-none">
                                {{ $log->action }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">{{ $log->message }}</p>
                                <p class="text-xs text-muted">
                                    {{ $log->created_at->format('d M Y H:i') }} &middot; {{ $log->performed_by }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <x-empty title="No suspension activity yet" />
                    @endforelse
                </div>
            </x-card>

            @if ($customer->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm text-muted">{{ $customer->notes }}</p>
                </x-card>
            @endif

            <x-card title="Danger zone">
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                      x-data="{ open: false }">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-muted">
                        Archiving removes the customer from active billing. Invoices and payments are kept for your records.
                    </p>
                    <button type="button" class="btn btn-danger mt-3 w-full" @click="open = true">Archive customer</button>

                    <template x-teleport="body">
                        <div class="modal-backdrop" x-show="open" x-cloak @keydown.escape.window="open = false">
                            <div class="modal-panel card-pad" @click.outside="open = false">
                                <p class="font-semibold">Archive {{ $customer->name }}?</p>
                                <p class="mt-1 text-sm text-muted">
                                    Type the customer name exactly to confirm. This cannot be undone from the interface.
                                </p>
                                <div class="mt-4">
                                    <label for="confirm_name" class="label label-req">Customer name</label>
                                    <input id="confirm_name" name="confirm_name" type="text" class="input" placeholder="{{ $customer->name }}">
                                </div>
                                @if ($stats['outstanding'] > 0)
                                    <div class="alert alert-warning mt-3">
                                        <span>This customer still owes {{ isp_money($stats['outstanding']) }}.</span>
                                    </div>
                                @endif
                                <div class="mt-5 flex justify-end gap-2">
                                    <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Archive customer</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </form>
            </x-card>
        </div>
    </div>
@endsection
