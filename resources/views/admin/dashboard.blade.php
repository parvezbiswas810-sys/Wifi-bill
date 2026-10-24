@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Live overview of collections, customers and routers')

@section('header-actions')
    <a href="{{ route('admin.mikrotik.index') }}" class="btn btn-secondary btn-sm hidden sm:inline-flex">
        <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M12 4V1L8 5l4 4V6a6 6 0 0 1 5.7 7.9l1.5 1.5A8 8 0 0 0 12 4Zm0 14a6 6 0 0 1-5.7-7.9L4.8 8.6A8 8 0 0 0 12 20v3l4-4-4-4v3Z"/></svg>
        Sync routers
    </a>
    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/></svg>
        <span class="hidden sm:inline">Add customer</span>
        <span class="sm:hidden">Add</span>
    </a>
@endsection

@section('content')
    {{-- ============ Primary money cards ============ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total collected" :value="isp_money($totalCollected)"
                     :hint="'Approved payments only'"
                     icon="M12 1v2a9 9 0 1 0 9 9h-2a7 7 0 1 1-7-7V1Zm1 5v5.6l4 2.4-1 1.6-4.6-2.7V6h1.6Z" />
        <x-stat-card label="Collected this month" :value="isp_money($collectedThisMonth)"
                     :hint="now()->format('F Y')"
                     icon="M3 17h18v4H3v-4Zm0-6h18v4H3v-4Zm1-7h16l1 5H3l1-5Z" />
        <x-stat-card label="Total outstanding" :value="isp_money($totalOutstanding)"
                     :hint="'Overdue: '.isp_money($overdueAmount)"
                     icon="M12 2 1 21h22L12 2Zm0 6 1 8h-2l1-8Zm0 11a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z" />
        <x-stat-card label="Invoiced this month" :value="isp_money($invoicedThisMonth)"
                     :hint="'All time: '.isp_money($totalInvoiced)"
                     icon="M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM9 12h8v1.5H9V12Zm0 4h8v1.5H9V16Z" />
    </div>

    {{-- ============ Network cards ============ --}}
    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
        <x-stat-card label="Billing customers" :value="number_format($billingCustomers)"
                     :hint="$totalCustomers.' total'" icon="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 2c-3.9 0-7 1.8-7 4v3h14v-3c0-2.2-3.1-4-7-4Z" />
        <x-stat-card label="PPP clients" :value="number_format($pppTotal)"
                     hint="Imported from router" icon="M12 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 12c4.4 0 8 2.2 8 5v3H4v-3c0-2.8 3.6-5 8-5Z" />
        <x-stat-card label="Active PPP" :value="number_format($pppActive)" tone="success"
                     hint="Live sessions" icon="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z" />
        <x-stat-card label="Inactive PPP" :value="number_format($pppInactive)"
                     hint="No live session" icon="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 6a1.25 1.25 0 1 1 0 2.5A1.25 1.25 0 0 1 12 8Zm1 9h-2v-6h2v6Z" />
        <x-stat-card label="Suspended" :value="number_format($suspendedCustomers)" tone="danger"
                     hint="Internet off" icon="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 15.5A1.5 1.5 0 1 1 12 14a1.5 1.5 0 0 1 0 3.5ZM13 12h-2V6h2v6Z" />
        <x-stat-card label="Pending approvals" :value="number_format($pendingApprovals)"
                     :tone="$pendingApprovals > 0 ? 'warning' : 'brand'"
                     hint="Awaiting verification" icon="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 10.6 4 2.4-1 1.6-5-3V6h2v6.6Z" />
    </div>

    {{-- ============ Router status ============ --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-1">
            <div class="flex items-center justify-between gap-3">
                <p class="font-semibold">MikroTik status</p>
                <span class="badge {{ $onlineRouters > 0 ? 'badge-success' : 'badge-danger' }}">
                    <span class="dot"></span>{{ $onlineRouters }}/{{ $routers->count() }} online
                </span>
            </div>

            <p class="mt-3 text-xs text-muted">
                Last synchronization:
                <span class="font-medium" style="color: var(--text)">
                    {{ $latestSync ? \Carbon\Carbon::parse($latestSync)->diffForHumans() : 'never' }}
                </span>
            </p>

            <div class="mt-4 space-y-2">
                @forelse ($routers as $router)
                    <div class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 surface-muted">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $router->name }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ $router->host }}:{{ $router->api_port }}
                                @if ($router->last_error)
                                    &middot; {{ Str::limit($router->last_error, 40) }}
                                @endif
                            </p>
                        </div>
                        <span class="badge {{ $router->isOnline() ? 'badge-success' : 'badge-danger' }}">
                            {{ $router->isOnline() ? 'Online' : 'Offline' }}
                        </span>
                    </div>
                @empty
                    <div class="alert alert-warning">
                        <span>No MikroTik router configured yet.
                            <a href="{{ route('admin.mikrotik.index') }}" class="font-semibold underline">Add one</a>
                            to enable PPP synchronization.</span>
                    </div>
                @endforelse
            </div>

            @if ($recentSyncLogs->isNotEmpty())
                <div class="divider my-4"></div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Recent router activity</p>
                <div class="mt-2 space-y-1.5">
                    @foreach ($recentSyncLogs as $log)
                        <div class="flex items-start gap-2 text-xs">
                            <span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }} flex-none">
                                {{ $log->type }}
                            </span>
                            <span class="min-w-0 flex-1 text-muted">{{ Str::limit($log->message, 70) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Charts --}}
        <div class="card lg:col-span-2">
            <div class="card-head">
                <div>
                    <p class="font-semibold">Collection trends</p>
                    <p class="text-xs text-muted">Approved payments only</p>
                </div>
                <div class="flex gap-1 rounded-xl p-1 surface-muted" x-data="{ tab: 'monthly' }"
                     x-init="$watch('tab', v => window.dispatchEvent(new CustomEvent('chart-tab', { detail: v })))">
                    <button type="button" class="btn btn-sm" :class="tab === 'monthly' ? 'btn-primary' : 'btn-ghost'"
                            @click="tab = 'monthly'">Monthly</button>
                    <button type="button" class="btn btn-sm" :class="tab === 'daily' ? 'btn-primary' : 'btn-ghost'"
                            @click="tab = 'daily'">Daily</button>
                </div>
            </div>
            <div class="card-pad">
                <div class="h-56 w-full">
                    <canvas id="collection-chart"></canvas>
                </div>
                <script type="application/json" id="chart-data">@json(['monthly' => $monthlyCollection, 'daily' => $dailyCollection])</script>
            </div>
        </div>
    </div>

    {{-- ============ Recent activity ============ --}}
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card">
            <div class="card-head">
                <p class="font-semibold">Recent payments</p>
                <a href="{{ route('admin.invoices.index') }}" class="text-xs font-semibold" style="color: var(--color-brand-600)">View all</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Customer</th><th>Invoice</th><th>Amount</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $payment->customer?->name ?? '-' }}</p>
                                    <p class="text-xs text-muted">{{ $payment->customer?->customer_code }}</p>
                                </td>
                                <td class="text-xs">{{ $payment->invoice?->invoice_number ?? '-' }}</td>
                                <td class="font-semibold">{{ isp_money($payment->amount) }}</td>
                                <td class="text-xs text-muted">{{ $payment->paid_at?->format('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty title="No payments recorded yet"
                                message="Approved payments will appear here." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <p class="font-semibold">Recent invoices</p>
                <a href="{{ route('admin.invoices.index') }}" class="text-xs font-semibold" style="color: var(--color-brand-600)">View all</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="font-medium"
                                       style="color: var(--color-brand-600)">{{ $invoice->invoice_number }}</a>
                                    <p class="text-xs text-muted">{{ $invoice->issue_date?->format('d M Y') }}</p>
                                </td>
                                <td class="text-sm">{{ $invoice->customer?->name ?? '-' }}</td>
                                <td class="font-semibold">{{ isp_money($invoice->amount) }}</td>
                                <td><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty title="No invoices yet"
                                message="Invoices are generated automatically on each customer's billing date." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ Upcoming suspensions + new customers ============ --}}
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card">
            <div class="card-head">
                <div>
                    <p class="font-semibold">Upcoming internet suspensions</p>
                    <p class="text-xs text-muted">Customers at risk if invoices stay unpaid</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Customer</th><th>PPP user</th><th>Outstanding</th><th>Suspension</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($upcomingSuspensions as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium"
                                       style="color: var(--color-brand-600)">{{ $customer->name }}</a>
                                    <p class="text-xs text-muted">{{ $customer->customer_code }}</p>
                                </td>
                                <td class="text-sm">{{ $customer->ppp_username ?? '-' }}</td>
                                <td class="font-semibold">{{ isp_money($customer->outstandingBalance()) }}</td>
                                <td>
                                    <span class="badge {{ $customer->daysUntilSuspension() !== null && $customer->daysUntilSuspension() <= 3 ? 'badge-danger' : 'badge-warning' }}">
                                        {{ $customer->next_suspension_date?->format('d M Y') }}
                                    </span>
                                    <p class="mt-1 text-xs text-muted">
                                        @if ($customer->daysUntilSuspension() === 0)
                                            today
                                        @elseif ($customer->daysUntilSuspension() !== null)
                                            in {{ $customer->daysUntilSuspension() }} day(s)
                                        @else
                                            overdue
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty title="No upcoming suspensions"
                                message="No customer is currently scheduled for suspension." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <p class="font-semibold">Recently added customers</p>
                <a href="{{ route('admin.customers.index') }}" class="text-xs font-semibold" style="color: var(--color-brand-600)">View all</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Customer</th><th>PPP user</th><th>Monthly bill</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCustomers as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium"
                                       style="color: var(--color-brand-600)">{{ $customer->name }}</a>
                                    <p class="text-xs text-muted">{{ $customer->customer_code }}</p>
                                </td>
                                <td class="text-sm">{{ $customer->ppp_username ?? '-' }}</td>
                                <td class="font-semibold">{{ isp_money($customer->monthly_bill_amount) }}</td>
                                <td>
                                    @php($label = $customer->internetStatusLabel())
                                    <span class="badge {{ match($label) {
                                        'Active' => 'badge-success',
                                        'Suspended' => 'badge-danger',
                                        'Router Unavailable' => 'badge-warning',
                                        'Archived' => 'badge-muted',
                                        default => 'badge-muted',
                                    } }}">{{ $label }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty title="No customers yet"
                                message="Add your first customer to start billing.">
                                <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm mt-2">Add customer</a>
                            </x-empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
