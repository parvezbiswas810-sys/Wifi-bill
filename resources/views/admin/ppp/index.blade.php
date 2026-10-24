@extends('layouts.app')

@section('title', 'PPP clients')
@section('subtitle', 'Imported from your MikroTik routers')

@section('header-actions')
    @if ($routers->isNotEmpty())
        <div x-data="{ busy: false }" class="inline-flex">
            <button type="button" class="btn btn-primary btn-sm" :disabled="busy"
                    @click="
                        busy = true;
                        Promise.all([...document.querySelectorAll('[data-router-sync]')].map(el =>
                            window.apiFetch(el.dataset.routerSync, { method: 'POST' })
                                .then(r => window.toast(r.message, 'success'))
                                .catch(e => window.toast(e.message, 'error'))
                        )).finally(() => { busy = false; setTimeout(() => location.reload(), 900); });
                    ">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M12 4V1L8 5l4 4V6a6 6 0 0 1 5.7 7.9l1.5 1.5A8 8 0 0 0 12 4Zm0 14a6 6 0 0 1-5.7-7.9L4.8 8.6A8 8 0 0 0 12 20v3l4-4-4-4v3Z"/></svg>
                <span x-show="! busy">Sync all routers</span>
                <span x-show="busy" x-cloak>Syncing...</span>
            </button>
            @foreach ($routers as $router)
                <span data-router-sync="{{ route('admin.mikrotik.sync', $router) }}" class="hidden"></span>
            @endforeach
        </div>
    @endif
@endsection

@section('content')
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Active sessions" :value="number_format($counts['active'])" tone="success" />
        <x-stat-card label="Inactive accounts" :value="number_format($counts['inactive'])" />
        <x-stat-card label="All PPP clients" :value="number_format($counts['all'])" />
        <x-stat-card label="Not assigned to a customer" :value="number_format($counts['unassigned'])"
                     :tone="$counts['unassigned'] > 0 ? 'warning' : 'brand'" />
    </div>

    <div class="card mt-4">
        {{-- Tabs --}}
        <div class="card-head">
            <div class="flex gap-1 rounded-xl p-1 surface-muted">
                @foreach (['active' => 'Active PPP', 'inactive' => 'Inactive PPP', 'all' => 'All PPP clients'] as $key => $label)
                    <a href="{{ route('admin.ppp.index', array_filter(['tab' => $key, 'q' => request('q'), 'router' => request('router')])) }}"
                       class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" class="card-head gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ request('q') }}" class="input sm:max-w-xs"
                       placeholder="Search username, caller ID, IP, profile or comment">
                <select name="router" class="select sm:max-w-[14rem]">
                    <option value="">All routers</option>
                    @foreach ($routers as $router)
                        <option value="{{ $router->id }}" @selected((int) request('router') === $router->id)>{{ $router->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                @if (request()->hasAny(['q', 'router']))
                    <a href="{{ route('admin.ppp.index', ['tab' => $tab]) }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>PPP username</th>
                        <th>Service</th>
                        <th>Profile</th>
                        <th>Caller ID</th>
                        <th>IP address</th>
                        <th>Session</th>
                        <th>Uptime</th>
                        <th>Billing</th>
                        <th>Customer</th>
                        <th>Last sync</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        @php($owner = $assigned[$account->id] ?? null)
                        <tr>
                            <td>
                                <p class="font-medium">{{ $account->username }}</p>
                                <p class="text-xs text-muted">{{ $account->router?->name }}</p>
                            </td>
                            <td class="text-sm">{{ $account->service ?? '-' }}</td>
                            <td class="text-sm">{{ $account->profile ?? '-' }}</td>
                            <td class="text-sm">{{ $account->caller_id ?: ($account->session_caller_id ?: '-') }}</td>
                            <td class="text-sm">{{ $account->remote_address ?: ($account->local_address ?: '-') }}</td>
                            <td>
                                @if ($account->is_active_session)
                                    <span class="badge badge-success"><span class="dot"></span>Online</span>
                                @else
                                    <span class="badge badge-muted">Offline</span>
                                @endif
                                @if ($account->disabled)
                                    <p class="mt-1"><span class="badge badge-danger">Disabled on router</span></p>
                                @endif
                            </td>
                            <td class="text-sm">{{ $account->session_uptime ?? '-' }}</td>
                            <td>
                                @if ($owner)
                                    <span class="badge badge-info">Billing enabled</span>
                                @else
                                    <span class="badge badge-muted">No customer</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                @if ($owner)
                                    <a href="{{ route('admin.customers.show', $owner->id) }}" class="font-medium"
                                       style="color: var(--color-brand-600)">{{ $owner->name }}</a>
                                    <p class="text-xs text-muted">{{ $owner->customer_code }}</p>
                                @else
                                    <a href="{{ route('admin.customers.create', ['router' => $account->router_id]) }}"
                                       class="text-xs font-semibold" style="color: var(--color-brand-600)">Assign</a>
                                @endif
                            </td>
                            <td class="text-xs text-muted">{{ $account->last_synced_at?->diffForHumans() ?? 'never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <x-empty title="{{ $tab === 'active' ? 'No active PPP sessions' : 'No PPP accounts found' }}"
                                    message="Run a synchronization to import PPP secrets and live sessions from your router.">
                                    <a href="{{ route('admin.mikrotik.index') }}" class="btn btn-primary btn-sm mt-2">Go to routers</a>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($accounts->hasPages())
            <div class="card-pad border-t border-app">{{ $accounts->links() }}</div>
        @endif
    </div>

    @if ($routers->contains(fn ($r) => ! $r->isOnline()))
        <div class="alert alert-warning mt-4">
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 flex-none"><path d="M12 2 1 21h22L12 2Zm0 6 1 8h-2l1-8Zm0 11a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>
            <span>One or more routers are unreachable. The information above reflects the last successful synchronization and may be stale.</span>
        </div>
    @endif
@endsection
