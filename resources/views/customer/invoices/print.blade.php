@extends('layouts.print')

@section('title', 'Invoice '.$invoice->invoice_number)
@section('back_url', route('customer.invoices.show', $invoice))

@section('content')
    <div class="card card-pad">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="brand-mark">{{ strtoupper(substr($invoice->customer?->isp?->name ?? 'ISP', 0, 2)) }}</span>
                    <div>
                        <p class="text-lg font-bold">{{ $invoice->customer?->isp?->name ?? config('app.name') }}</p>
                        <p class="text-xs text-muted">{{ $invoice->customer?->isp?->address }}</p>
                    </div>
                </div>
                <div class="mt-3 text-xs text-muted">
                    @if ($invoice->customer?->isp?->contact_number) <p>Phone: {{ $invoice->customer->isp->contact_number }}</p> @endif
                    @if ($invoice->customer?->isp?->email) <p>Email: {{ $invoice->customer->isp->email }}</p> @endif
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Invoice</p>
                <p class="text-xl font-bold">{{ $invoice->invoice_number }}</p>
                <p class="mt-1 text-xs text-muted">Issued {{ $invoice->issue_date->format('d M Y') }}</p>
                <p class="text-xs text-muted">Due {{ $invoice->due_date->format('d M Y') }}</p>
                <p class="mt-2"><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></p>
            </div>
        </div>

        <div class="divider my-5"></div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Billed to</p>
                <p class="mt-1 font-semibold">{{ $invoice->customer?->name }}</p>
                <p class="text-sm text-muted">{{ $invoice->customer?->customer_code }}</p>
                <p class="text-sm text-muted">{{ $invoice->customer?->phone }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Service</p>
                <p class="mt-1 text-sm">PPP username: {{ $invoice->customer?->ppp_username ?? '-' }}</p>
                <p class="text-sm">Period: {{ $invoice->billing_period_start->format('d M Y') }} - {{ $invoice->billing_period_end->format('d M Y') }}</p>
            </div>
        </div>

        <div class="table-wrap mt-6">
            <table class="table">
                <thead><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr><td>{{ $item->description }}</td><td class="text-right">{{ isp_money($item->amount) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex justify-end">
            <dl class="w-full max-w-xs">
                <div class="kv"><dt>Subtotal</dt><dd>{{ isp_money($invoice->amount) }}</dd></div>
                <div class="kv"><dt>Paid</dt><dd>{{ isp_money($invoice->amount_paid) }}</dd></div>
                <div class="kv text-base"><dt class="font-semibold">Balance due</dt><dd class="font-bold">{{ isp_money($invoice->balance) }}</dd></div>
            </dl>
        </div>

        <div class="divider my-5"></div>
        <p class="text-center text-xs text-muted">Thank you for your business.</p>
    </div>
@endsection
