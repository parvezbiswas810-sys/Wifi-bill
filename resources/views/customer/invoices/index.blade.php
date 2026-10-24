@extends('layouts.customer')

@section('title', 'My invoices')
@section('subtitle', 'Your complete billing history')

@section('content')
    <div class="grid grid-cols-3 gap-4">
        <x-stat-card label="Total invoiced" :value="isp_money($totals['invoiced'])" />
        <x-stat-card label="Total paid" :value="isp_money($totals['paid'])" tone="success" />
        <x-stat-card label="Outstanding" :value="isp_money($totals['outstanding'])"
                     :tone="$totals['outstanding'] > 0 ? 'warning' : 'success'" />
    </div>

    <div class="card mt-4">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Invoice</th><th>Period</th><th>Issue</th><th>Due</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="font-medium">{{ $invoice->invoice_number }}</td>
                            <td class="text-sm">{{ $invoice->billing_period_start->format('M Y') }}</td>
                            <td class="text-sm">{{ $invoice->issue_date->format('d M Y') }}</td>
                            <td class="text-sm">{{ $invoice->due_date->format('d M Y') }}</td>
                            <td class="font-semibold">{{ isp_money($invoice->amount) }}</td>
                            <td>{{ isp_money($invoice->amount_paid) }}</td>
                            <td>{{ isp_money($invoice->balance) }}</td>
                            <td><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('customer.invoices.show', $invoice) }}" class="btn btn-secondary btn-sm">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty title="No invoices yet"
                            message="Your invoices will appear here after they are generated." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="card-pad border-t border-app">{{ $invoices->links() }}</div>
        @endif
    </div>
@endsection
