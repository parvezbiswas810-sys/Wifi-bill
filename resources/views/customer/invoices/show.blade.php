@extends('layouts.customer')

@section('title', 'Invoice '.$invoice->invoice_number)
@section('subtitle', $invoice->billing_period_start->format('F Y'))

@section('content')
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Invoice details">
                <div class="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                    <dl>
                        <div class="kv"><dt>Invoice number</dt><dd>{{ $invoice->invoice_number }}</dd></div>
                        <div class="kv"><dt>Billing period</dt>
                            <dd>{{ $invoice->billing_period_start->format('d M Y') }} - {{ $invoice->billing_period_end->format('d M Y') }}</dd></div>
                        <div class="kv"><dt>Issue date</dt><dd>{{ $invoice->issue_date->format('d M Y') }}</dd></div>
                    </dl>
                    <dl>
                        <div class="kv"><dt>Due date</dt><dd>{{ $invoice->due_date->format('d M Y') }}</dd></div>
                        <div class="kv"><dt>Status</dt>
                            <dd><span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span></dd></div>
                        <div class="kv"><dt>Amount</dt><dd>{{ isp_money($invoice->amount) }}</dd></div>
                    </dl>
                </div>
            </x-card>

            <x-card title="Items" class="mt-4" :pad="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-right">{{ isp_money($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td class="text-right font-semibold">Total</td><td class="text-right font-semibold">{{ isp_money($invoice->amount) }}</td></tr>
                            <tr><td class="text-right">Paid</td><td class="text-right">{{ isp_money($invoice->amount_paid) }}</td></tr>
                            <tr><td class="text-right font-semibold">Balance due</td><td class="text-right font-bold">{{ isp_money($invoice->balance) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-4">
            @if ((float) $invoice->balance > 0 && $invoice->status !== 'cancelled')
                <x-card title="Payment">
                    <p class="text-sm text-muted">Outstanding balance on this invoice:</p>
                    <p class="mt-1 text-xl font-bold">{{ isp_money($invoice->balance) }}</p>
                    <a href="{{ route('customer.payments.create', ['invoice' => $invoice->id]) }}" class="btn btn-primary mt-3 w-full">
                        Make payment
                    </a>
                    <p class="mt-3 text-xs text-muted">
                        Your payment is verified by the ISP before it is credited to your account.
                    </p>
                </x-card>
            @endif

            <x-card title="Actions">
                <a href="{{ route('customer.invoices.print', $invoice) }}" target="_blank" class="btn btn-secondary w-full">
                    Print / save as PDF
                </a>
            </x-card>

            @if ($invoice->payments->isNotEmpty())
                <x-card title="Payments received" :pad="false">
                    <div class="divide-y border-app border-t">
                        @foreach ($invoice->payments as $payment)
                            <div class="px-4 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium">{{ isp_money($payment->amount) }}</p>
                                    <span class="text-xs text-muted">{{ $payment->paid_at?->format('d M Y') }}</span>
                                </div>
                                <p class="text-xs text-muted">
                                    {{ $payment->method_name ?? 'Payment' }}
                                    @if ($payment->reference) &middot; {{ $payment->reference }} @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
@endsection
