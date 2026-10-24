@extends('layouts.customer')

@section('title', 'My profile')
@section('subtitle', 'Your account details')

@section('content')
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card title="Account details">
            <dl>
                <div class="kv"><dt>Name</dt><dd>{{ $customer->name }}</dd></div>
                <div class="kv"><dt>Customer ID</dt><dd>{{ $customer->customer_code }}</dd></div>
                <div class="kv"><dt>Username</dt><dd>{{ $customer->username }}</dd></div>
                <div class="kv"><dt>Phone</dt><dd>{{ $customer->phone ?? '-' }}</dd></div>
                <div class="kv"><dt>Address</dt><dd>{{ $customer->address ?? '-' }}</dd></div>
                <div class="kv"><dt>PPP username</dt><dd>{{ $customer->ppp_username ?? '-' }}</dd></div>
                <div class="kv"><dt>Monthly bill</dt><dd>{{ isp_money($customer->monthly_bill_amount) }}</dd></div>
                <div class="kv"><dt>Member since</dt><dd>{{ $customer->created_at?->format('d M Y') }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-muted">
                To change your name, phone or address, please contact your ISP.
            </p>
        </x-card>

        <div class="space-y-4">
            <x-card title="Change password">
                <form method="POST" action="{{ route('customer.profile.password') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="current_password" class="label label-req">Current password</label>
                        <input id="current_password" name="current_password" type="password" required class="input" autocomplete="current-password">
                        @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="label label-req">New password</label>
                        <input id="password" name="password" type="password" required class="input" autocomplete="new-password">
                        <p class="hint">At least 6 characters.</p>
                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="label label-req">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="input" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Update password</button>
                </form>
            </x-card>

            <x-card title="Need help?">
                <p class="text-sm text-muted">Contact your internet service provider:</p>
                <dl class="mt-3">
                    @if ($isp?->contact_number)
                        <div class="kv"><dt>Phone</dt><dd>{{ $isp->contact_number }}</dd></div>
                    @endif
                    @if ($isp?->email)
                        <div class="kv"><dt>Email</dt><dd>{{ $isp->email }}</dd></div>
                    @endif
                    @if ($isp?->website)
                        <div class="kv"><dt>Website</dt><dd>{{ $isp->website }}</dd></div>
                    @endif
                </dl>
            </x-card>
        </div>
    </div>
@endsection
