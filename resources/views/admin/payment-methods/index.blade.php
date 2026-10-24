@extends('layouts.app')

@section('title', 'Payment methods')
@section('subtitle', 'What customers see when they submit a payment')

@section('content')
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            @forelse ($methods as $method)
                <div class="card" x-data="{ edit: false }">
                    <div class="card-head">
                        <div class="flex items-center gap-3">
                            <span class="stat-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7Zm0 3h20v2H2v-2Zm3 5h5v2H5v-2Z"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold">{{ $method->name }}</p>
                                <p class="text-xs text-muted">
                                    {{ ucfirst($method->type) }}
                                    @if ($method->account_number) &middot; {{ $method->account_number }} @endif
                                </p>
                            </div>
                            <span class="badge {{ $method->is_enabled ? 'badge-success' : 'badge-muted' }}">
                                {{ $method->is_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" @click="edit = ! edit">Edit</button>
                    </div>

                    @if ($method->instructions)
                        <div class="card-pad pt-3">
                            <p class="text-sm text-muted">{{ $method->instructions }}</p>
                        </div>
                    @endif

                    <div class="card-pad pt-3 flex flex-wrap gap-2 text-xs">
                        @if ($method->requires_reference)<span class="badge badge-info">Reference required</span>@endif
                        @if ($method->requires_sender)<span class="badge badge-info">Sender number required</span>@endif
                        @if ($method->account_name)<span class="badge badge-muted">Account name: {{ $method->account_name }}</span>@endif
                    </div>

                    <div class="border-t border-app" x-show="edit" x-cloak>
                        <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="card-pad">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label label-req">Method name</label>
                                    <input name="name" type="text" required value="{{ $method->name }}" class="input">
                                </div>
                                <div>
                                    <label class="label label-req">Type</label>
                                    <select name="type" class="select">
                                        @foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'bank' => 'Bank transfer', 'cash' => 'Cash', 'custom' => 'Other'] as $val => $label)
                                            <option value="{{ $val }}" @selected($method->type === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Account number / merchant details</label>
                                    <input name="account_number" type="text" value="{{ $method->account_number }}" class="input">
                                </div>
                                <div>
                                    <label class="label">Account holder name</label>
                                    <input name="account_name" type="text" value="{{ $method->account_name }}" class="input">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Customer instructions</label>
                                    <textarea name="instructions" rows="3" class="textarea">{{ $method->instructions }}</textarea>
                                    <p class="hint">Shown on the payment page. Never include private API keys or merchant secrets here.</p>
                                </div>
                                <div>
                                    <label class="label">Display order</label>
                                    <input name="sort_order" type="number" min="0" max="999" value="{{ $method->sort_order }}" class="input">
                                </div>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="is_enabled" value="1" class="checkbox" @checked($method->is_enabled)>
                                        Enabled for customers
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="requires_reference" value="1" class="checkbox" @checked($method->requires_reference)>
                                        Require a transaction reference
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="requires_sender" value="1" class="checkbox" @checked($method->requires_sender)>
                                        Require the sender number
                                    </label>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap justify-between gap-2">
                                <button type="submit" form="delete-method-{{ $method->id }}" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Remove this payment method?')">Delete</button>
                                <div class="flex gap-2">
                                    <button type="button" class="btn btn-secondary btn-sm" @click="edit = false">Cancel</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                                </div>
                            </div>
                        </form>

                        <form id="delete-method-{{ $method->id }}" method="POST"
                              action="{{ route('admin.payment-methods.destroy', $method) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>
            @empty
                <div class="card">
                    <x-empty title="No payment methods configured"
                        message="Add bKash, Nagad, Rocket, bank transfer or cash so customers can submit payments." />
                </div>
            @endforelse
        </div>

        <div class="space-y-4">
            <x-card title="Add a payment method">
                <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="label label-req">Method name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name') }}" class="input"
                               placeholder="e.g. bKash Personal">
                        @error('name')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="type" class="label label-req">Type</label>
                        <select id="type" name="type" class="select">
                            @foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'bank' => 'Bank transfer', 'cash' => 'Cash', 'custom' => 'Other'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('type') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="account_number" class="label">Account number / merchant details</label>
                        <input id="account_number" name="account_number" type="text" value="{{ old('account_number') }}" class="input">
                    </div>
                    <div>
                        <label for="account_name" class="label">Account holder name</label>
                        <input id="account_name" name="account_name" type="text" value="{{ old('account_name') }}" class="input">
                    </div>
                    <div>
                        <label for="instructions" class="label">Customer instructions</label>
                        <textarea id="instructions" name="instructions" rows="3" class="textarea"
                                  placeholder="e.g. Send money to this number, then submit the transaction ID.">{{ old('instructions') }}</textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_enabled" value="1" class="checkbox" checked> Enabled for customers
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="requires_reference" value="1" class="checkbox" checked> Require a transaction reference
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="requires_sender" value="1" class="checkbox" checked> Require the sender number
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Add payment method</button>
                </form>
            </x-card>

            <div class="card card-pad">
                <p class="font-semibold">How verification works</p>
                <ul class="mt-3 space-y-2 text-sm text-muted">
                    <li>A customer submission is recorded as <span class="badge badge-info">Pending</span>.</li>
                    <li>Pending payments never increase your collected balance.</li>
                    <li>Only an approved submission records a payment and updates the invoice.</li>
                    <li>Approving a payment restores internet when the invoice is fully settled and billing owns the suspension.</li>
                    <li>An approved submission cannot be recorded twice, even if the button is clicked repeatedly.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
