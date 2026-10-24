@extends('layouts.app')

@section('title', 'Customers')
@section('subtitle', 'Every customer with billing enabled')

@section('header-actions')
    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/></svg>
        Add customer
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total" :value="number_format($summary['total'])" />
        <x-stat-card label="Billing active" :value="number_format($summary['active'])" tone="success" />
        <x-stat-card label="Internet suspended" :value="number_format($summary['suspended'])" tone="danger" />
        <x-stat-card label="Archived" :value="number_format($summary['archived'])" />
    </div>

    <div class="card mt-4">
        <form method="GET" class="card-head gap-3">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ request('q') }}" class="input sm:max-w-xs"
                       placeholder="Search name, code, username, PPP user or phone">
                <select name="internet_status" class="select sm:max-w-[11rem]">
                    <option value="">All internet states</option>
                    @foreach (['active' => 'Active', 'offline' => 'Offline', 'suspended' => 'Suspended'] as $val => $label)
                        <option value="{{ $val }}" @selected(request('internet_status') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="billing" class="select sm:max-w-[10rem]">
                    <option value="">All billing</option>
                    <option value="enabled" @selected(request('billing') === 'enabled')>Billing on</option>
                    <option value="disabled" @selected(request('billing') === 'disabled')>Billing off</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                @if (request()->hasAny(['q', 'internet_status', 'billing']))
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>PPP user</th>
                        <th>Monthly bill</th>
                        <th>Next bill</th>
                        <th>Internet off</th>
                        <th>Days left</th>
                        <th>Payment</th>
                        <th>Internet</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php($latest = $customer->latestInvoice())
                        <tr>
                            <td>
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium"
                                   style="color: var(--color-brand-600)">{{ $customer->name }}</a>
                                <p class="text-xs text-muted">{{ $customer->customer_code }} &middot; {{ $customer->username }}</p>
                            </td>
                            <td class="text-sm">{{ $customer->phone ?? '-' }}</td>
                            <td class="text-sm">
                                {{ $customer->ppp_username ?? '-' }}
                                @if ($customer->pppAccount)
                                    <p class="text-xs text-muted">
                                        {{ $customer->pppAccount->is_active_session ? 'Session online' : 'No session' }}
                                    </p>
                                @endif
                            </td>
                            <td class="font-semibold">{{ isp_money($customer->monthly_bill_amount) }}</td>
                            <td class="text-sm">{{ $customer->next_bill_date?->format('d M Y') ?? '-' }}</td>
                            <td class="text-sm">{{ $customer->next_suspension_date?->format('d M Y') ?? '-' }}</td>
                            <td class="text-sm">
                                @php($days = $customer->daysUntilSuspension())
                                @if ($days === null)
                                    <span class="text-muted">-</span>
                                @elseif ($days === 0)
                                    <span class="badge badge-danger">Today</span>
                                @else
                                    <span class="badge {{ $days <= 3 ? 'badge-warning' : 'badge-muted' }}">{{ $days }} day(s)</span>
                                @endif
                            </td>
                            <td>
                                @if ($latest)
                                    <span class="badge {{ $latest->badgeClass() }}">{{ $latest->statusLabel() }}</span>
                                @else
                                    <span class="badge badge-muted">No invoice</span>
                                @endif
                            </td>
                            <td>
                                @php($label = $customer->internetStatusLabel())
                                <span class="badge {{ match($label) {
                                    'Active' => 'badge-success',
                                    'Suspended' => 'badge-danger',
                                    'Router Unavailable' => 'badge-warning',
                                    default => 'badge-muted',
                                } }}">{{ $label }}</span>
                            </td>
                            <td>
                                <div class="flex justify-end" x-data="{ open: false }">
                                    <div class="relative" @click.outside="open = false">
                                        <button type="button" class="three-dot-btn !h-8 !w-8" @click="open = !open"
                                                aria-label="Actions for {{ $customer->name }}">
                                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M12 5.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z"/></svg>
                                        </button>
                                        <div class="menu-panel" x-show="open" x-cloak>
                                            <a href="{{ route('admin.customers.show', $customer) }}" class="menu-item">View details</a>
                                            <a href="{{ route('admin.customers.edit', $customer) }}" class="menu-item">Edit customer</a>
                                            <a href="{{ route('admin.invoices.index', ['customer' => $customer->id]) }}" class="menu-item">View invoices</a>
                                            <a href="{{ route('admin.payments.index', ['q' => $customer->customer_code]) }}" class="menu-item">Payment history</a>
                                            <div class="divider my-1"></div>
                                            <form method="POST" action="{{ route('admin.customers.toggle-internet', $customer) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="{{ $customer->internet_status === 'suspended' ? 'restore' : 'suspend' }}">
                                                <input type="hidden" name="reason" value="manual action from customer list">
                                                <button type="submit" class="menu-item w-full">
                                                    {{ $customer->internet_status === 'suspended' ? 'Restore internet' : 'Suspend internet' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.customers.toggle-billing', $customer) }}">
                                                @csrf
                                                <button type="submit" class="menu-item w-full">
                                                    {{ $customer->billing_enabled ? 'Disable billing' : 'Enable billing' }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <x-empty title="No customers found"
                                    message="Adjust your filters, or add your first customer.">
                                    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm mt-2">Add customer</a>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="card-pad border-t border-app">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection
