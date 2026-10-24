@extends('layouts.customer')

@section('title', 'Welcome, '.$customer->name)
@section('subtitle', 'Your billing and connection overview')

@section('content')
    {{-- ============ Key figures ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Outstanding balance" :value="isp_money($stats['outstanding'])"
                     :tone="$stats['outstanding'] > 0 ? 'warning' : 'success'"
                     :hint="$stats['outstanding'] > 0 ? 'Payment required' : 'All settled'" />
        <x-stat-card label="Monthly bill" :value="isp_money($customer->monthly_bill_amount)" />
        <x-stat-card label="Next bill date" :value="$customer->next_bill_date?->format('d M Y') ?? '-'"
                     :hint="$stats['days_to_bill'] !== null ? $stats['days_to_bill'].' day(s) remaining' : 'date passed'" />
        <x-stat-card label="Internet" :value="$customer->internetStatusLabel()"
                     :tone="$customer->internetStatusLabel() === 'Active' ? 'success' : ($customer->internetStatusLabel() === 'Suspended' ? 'danger' : 'warning')" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- ============ Latest invoice ============ --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card title="Latest invoice">
                @if ($latestInvoice)
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-lg font-bold">{{ $latestInvoice->invoice_number }}</p>
                            <p class="text-sm text-muted">
                                {{ $latestInvoice->billing_period_start->format('d M Y') }} -
                                {{ $latestInvoice->billing_period_end->format('d M Y') }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                                <div>
                                    <p class="text-xs text-muted">Issue date</p>
                                    <p class="font-medium">{{ $latestInvoice->issue_date->format('d M Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Due date</p>
                                    <p class="font-medium">{{ $latestInvoice->due_date->format('d M Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Amount</p>
                                    <p class="font-semibold">{{ isp_money($latestInvoice->amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Balance</p>
                                    <p class="font-semibold">{{ isp_money($latestInvoice->balance) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="badge {{ $latestInvoice->badgeClass() }}">{{ $latestInvoice->statusLabel() }}</span>
                            <div class="mt-3 flex flex-col gap-2">
                                <a href="{{ route('customer.invoices.show', $latestInvoice) }}" class="btn btn-secondary btn-sm">View invoice</a>
                                @if ((float) $latestInvoice->balance > 0 && ! $pendingSubmission)
                                    <a href="{{ route('customer.payments.create', ['invoice' => $latestInvoice->id]) }}" class="btn btn-primary btn-sm">Pay now</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($pendingSubmission)
                        <div class="alert alert-info mt-4">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 flex-none"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 10.6 4 2.4-1 1.6-5-3V6h2v6.6Z"/></svg>
                            <span>You have a payment of {{ isp_money($pendingSubmission->amount) }} awaiting administrator verification.
                                It will be credited once approved.</span>
                        </div>
                    @endif
                @else
                    <x-empty title="No invoice yet"
                        message="Your first invoice will be generated on {{ $customer->next_bill_date?->format('d M Y') ?? 'your billing date' }}." />
                @endif
            </x-card>

            <x-card title="Recent invoices" :pad="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Invoice</th><th>Period</th><th>Due</th><th>Amount</th><th>Balance</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('customer.invoices.show', $invoice) }}" class="font-medium"
                                           style="color: var(--color-brand-600)">{{ $invoice->invoice_number }}</a>
                                    </td>
                                    <td class="text-sm">{{ $invoice->billing_period_start->format('M Y') }}</td>
                                    <td class="text-sm">{{ $invoice->due_date->format('d M Y') }}</td>
                                    <td class="font-semibold">{{ isp_money($invoice->amount) }}</td>
                                    <td>{{ isp_money($invoice->balance) }}</td>
                                    <td><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty title="No invoices yet" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ============ Right column ============ --}}
        <div class="space-y-4">
            <x-card title="My account">
                <dl>
                    <div class="kv"><dt>Customer ID</dt><dd>{{ $customer->customer_code }}</dd></div>
                    <div class="kv"><dt>Phone</dt><dd>{{ $customer->phone ?? '-' }}</dd></div>
                    <div class="kv"><dt>PPP username</dt><dd>{{ $customer->ppp_username ?? '-' }}</dd></div>
                    <div class="kv"><dt>Connection</dt>
                        <dd>
                            @if ($customer->isOnline())
                                <span class="badge badge-success"><span class="dot"></span>Online</span>
                            @elseif ($customer->router && ! $customer->router->isOnline())
                                <span class="badge badge-warning">Router unavailable</span>
                            @else
                                <span class="badge badge-muted">Offline</span>
                            @endif
                        </dd>
                    </div>
                    <div class="kv"><dt>Internet status</dt><dd>{{ $customer->internetStatusLabel() }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Payment summary">
                <dl>
                    <div class="kv"><dt>Total invoiced</dt><dd>{{ isp_money($stats['invoiced_total']) }}</dd></div>
                    <div class="kv"><dt>Total paid</dt><dd>{{ isp_money($stats['paid_total']) }}</dd></div>
                    <div class="kv"><dt>Outstanding</dt><dd>{{ isp_money($stats['outstanding']) }}</dd></div>
                    <div class="kv"><dt>Last payment</dt>
                        <dd>{{ $lastPayment ? $lastPayment->paid_at?->format('d M Y') : 'none' }}</dd></div>
                    <div class="kv"><dt>Last payment amount</dt>
                        <dd>{{ $lastPayment ? isp_money($lastPayment->amount) : '-' }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Schedule">
                <dl>
                    <div class="kv"><dt>Next bill generation</dt><dd>{{ $customer->next_bill_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div class="kv"><dt>Days until next bill</dt>
                        <dd>
                            @if ($stats['days_to_bill'] === null)
                                <span class="badge badge-warning">Date passed</span>
                            @else
                                {{ $stats['days_to_bill'] }} day(s)
                            @endif
                        </dd>
                    </div>
                    <div class="kv"><dt>Scheduled suspension</dt><dd>{{ $customer->next_suspension_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div class="kv"><dt>Days until suspension</dt>
                        <dd>
                            @if ($stats['days_to_suspension'] === null)
                                @if ($customer->internet_status === 'suspended')
                                    <span class="badge badge-danger">Suspended</span>
                                @else
                                    <span class="text-muted">Not scheduled</span>
                                @endif
                            @elseif ($stats['days_to_suspension'] === 0)
                                <span class="badge badge-danger">Today</span>
                            @else
                                {{ $stats['days_to_suspension'] }} day(s)
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            @if ($notifications->isNotEmpty())
                <x-card title="Recent updates">
                    <div class="space-y-3">
                        @foreach ($notifications as $notification)
                            <div class="flex items-start gap-2">
                                <span class="dot mt-1.5 flex-none" style="color: var(--color-brand-500)"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{{ $notification->title }}</p>
                                    <p class="text-xs text-muted">{{ $notification->body }}</p>
                                    <p class="mt-0.5 text-xs text-muted">{{ $notification->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
@endsection
