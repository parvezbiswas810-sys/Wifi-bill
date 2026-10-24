@extends('layouts.app')

@section('title', 'Billing history')
@section('subtitle', 'Invoices, payments and outstanding balances')

@section('header-actions')
    <form method="POST" action="{{ route('admin.invoices.generate') }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M12 4V1L8 5l4 4V6a6 6 0 0 1 5.7 7.9l1.5 1.5A8 8 0 0 0 12 4Zm0 14a6 6 0 0 1-5.7-7.9L4.8 8.6A8 8 0 0 0 12 20v3l4-4-4-4v3Z"/></svg>
            Generate due invoices
        </button>
    </form>
@endsection

@section('content')
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total invoiced" :value="isp_money($totals['invoiced'])" />
        <x-stat-card label="Total collected" :value="isp_money($totals['collected'])" tone="success" />
        <x-stat-card label="Unpaid balance" :value="isp_money($totals['unpaid'])" tone="warning" />
        <x-stat-card label="Overdue balance" :value="isp_money($totals['overdue'])" tone="danger" />
    </div>

    <div class="card mt-4">
        <form method="GET" class="card-head gap-3">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ request('q') }}" class="input sm:max-w-xs"
                       placeholder="Invoice number, customer or code">
                <select name="customer" class="select sm:max-w-[13rem]">
                    <option value="">All customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected((int) request('customer') === $c->id)>
                            {{ $c->name }} ({{ $c->customer_code }})
                        </option>
                    @endforeach
                </select>
                <select name="status" class="select sm:max-w-[11rem]">
                    <option value="">All statuses</option>
                    @foreach (['paid' => 'Paid', 'unpaid' => 'Unpaid', 'partial' => 'Partially paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'] as $val => $label)
                        <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="input sm:max-w-[10rem]" aria-label="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="input sm:max-w-[10rem]" aria-label="To date">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                @if (request()->hasAny(['q', 'customer', 'status', 'from', 'to']))
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th><th>Customer</th><th>Period</th><th>Issue</th><th>Due</th>
                        <th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="font-medium"
                                   style="color: var(--color-brand-600)">{{ $invoice->invoice_number }}</a>
                            </td>
                            <td>
                                <p class="text-sm font-medium">{{ $invoice->customer?->name }}</p>
                                <p class="text-xs text-muted">{{ $invoice->customer?->customer_code }}</p>
                            </td>
                            <td class="text-sm">{{ $invoice->billing_period_start->format('M Y') }}</td>
                            <td class="text-sm">{{ $invoice->issue_date->format('d M Y') }}</td>
                            <td class="text-sm">{{ $invoice->due_date->format('d M Y') }}</td>
                            <td class="font-semibold">{{ isp_money($invoice->amount) }}</td>
                            <td>{{ isp_money($invoice->amount_paid) }}</td>
                            <td class="{{ (float) $invoice->balance > 0 ? 'font-semibold' : '' }}">{{ isp_money($invoice->balance) }}</td>
                            <td><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end" x-data="{ open: false }">
                                    <div class="relative" @click.outside="open = false">
                                        <button type="button" class="three-dot-btn !h-8 !w-8" @click="open = !open"
                                                aria-label="Actions for {{ $invoice->invoice_number }}">
                                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M12 5.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z"/></svg>
                                        </button>
                                        <div class="menu-panel" x-show="open" x-cloak>
                                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="menu-item">View invoice</a>
                                            <a href="{{ route('admin.invoices.print', $invoice) }}" target="_blank" class="menu-item">Print invoice</a>
                                            <a href="{{ route('admin.invoices.print', $invoice) }}" class="menu-item">Download / save as PDF</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty title="No invoices found"
                            message="Adjust your filters, or run invoice generation for customers whose billing date has arrived." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="card-pad border-t border-app">{{ $invoices->links() }}</div>
        @endif
    </div>
@endsection
