@extends('layouts.customer')

@section('title', 'Make a payment')
@section('subtitle', 'Submit your payment details for verification')

@section('content')
    @if ($invoices->isEmpty())
        <div class="card">
            <x-empty title="Nothing to pay right now"
                message="You have no outstanding invoices. Any future invoice will appear here." />
        </div>
    @else
        <form method="POST" action="{{ route('customer.payments.store') }}" enctype="multipart/form-data"
              class="grid grid-cols-1 gap-4 lg:grid-cols-3" x-data="{ methodId: '{{ old('payment_method_id', $methods->first()->id ?? '') }}' }">
            @csrf

            <div class="lg:col-span-2 space-y-4">
                <x-card title="Which invoice are you paying?">
                    <div class="space-y-3">
                        @foreach ($invoices as $invoice)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-app p-3 hover:surface-muted">
                                <input type="radio" name="invoice_id" value="{{ $invoice->id }}" class="mt-1 checkbox" required
                                       @checked((int) old('invoice_id', $selected?->id) === $invoice->id)>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold">{{ $invoice->invoice_number }}</span>
                                        <span class="badge {{ $invoice->badgeClass() }}">{{ $invoice->statusLabel() }}</span>
                                    </span>
                                    <span class="mt-1 block text-sm text-muted">
                                        Period {{ $invoice->billing_period_start->format('M Y') }}
                                        &middot; Due {{ $invoice->due_date->format('d M Y') }}
                                    </span>
                                    <span class="mt-1 block text-sm">
                                        Amount {{ isp_money($invoice->amount) }}
                                        &middot; <span class="font-semibold">Balance {{ isp_money($invoice->balance) }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('invoice_id')<p class="field-error">{{ $message }}</p>@enderror
                </x-card>

                <x-card title="Payment method">
                    @if ($methods->isEmpty())
                        <div class="alert alert-warning">
                            <span>No payment method is available yet. Please contact your ISP.</span>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($methods as $method)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-app p-3 hover:surface-muted">
                                    <input type="radio" name="payment_method_id" value="{{ $method->id }}" class="mt-1 checkbox" required
                                           x-model="methodId"
                                           @checked((int) old('payment_method_id') === $method->id)>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold">{{ $method->name }}</span>
                                        @if ($method->account_number)
                                            <span class="mt-1 block text-sm">
                                                Send to: <span class="font-medium">{{ $method->account_number }}</span>
                                                @if ($method->account_name) ({{ $method->account_name }}) @endif
                                            </span>
                                        @endif
                                        @if ($method->instructions)
                                            <span class="mt-1 block text-sm text-muted">{{ $method->instructions }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method_id')<p class="field-error">{{ $message }}</p>@enderror
                    @endif
                </x-card>

                <x-card title="Payment details">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount" class="label label-req">Amount paid</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
                                   value="{{ old('amount', $selected?->balance) }}"
                                   class="input @error('amount') input-error @enderror">
                            @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="sender_number" class="label">Your sender number</label>
                            <input id="sender_number" name="sender_number" type="text"
                                   value="{{ old('sender_number') }}"
                                   class="input @error('sender_number') input-error @enderror"
                                   placeholder="The number you paid from">
                            @error('sender_number')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="reference" class="label">Transaction ID / reference</label>
                            <input id="reference" name="reference" type="text" value="{{ old('reference') }}"
                                   class="input @error('reference') input-error @enderror"
                                   placeholder="e.g. 8N7A2K9L01">
                            @error('reference')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="screenshot" class="label">Payment screenshot (optional)</label>
                            <input id="screenshot" name="screenshot" type="file" accept="image/*"
                                   class="input @error('screenshot') input-error @enderror">
                            <p class="hint">JPG, PNG or WebP, up to 2 MB. Only your ISP can see this file.</p>
                            @error('screenshot')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="space-y-4">
                <x-card title="Before you submit">
                    <ul class="space-y-2 text-sm text-muted">
                        <li>Send the money first, then submit the transaction details here.</li>
                        <li>Your payment stays <span class="badge badge-info">Pending</span> until the ISP verifies it.</li>
                        <li>A pending payment does not clear your balance yet.</li>
                        <li>Once approved, your invoice updates and internet is restored automatically if it was suspended for non-payment.</li>
                        <li>The same transaction ID cannot be submitted twice.</li>
                    </ul>
                    <button type="submit" class="btn btn-primary mt-4 w-full">Submit payment</button>
                    <a href="{{ route('customer.dashboard') }}" class="btn btn-secondary mt-2 w-full">Cancel</a>
                </x-card>
            </div>
        </form>
    @endif
@endsection
