@extends('layouts.customer')

@section('title', 'My connection')
@section('subtitle', 'Your service status and history')

@section('content')
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card title="Connection details">
            <dl>
                <div class="kv"><dt>PPP username</dt><dd>{{ $customer->ppp_username ?? '-' }}</dd></div>
                <div class="kv"><dt>Service profile</dt><dd>{{ $customer->pppAccount?->profile ?? '-' }}</dd></div>
                <div class="kv"><dt>Assigned address</dt><dd>{{ $customer->pppAccount?->remote_address ?? $customer->pppAccount?->local_address ?? '-' }}</dd></div>
                <div class="kv"><dt>Session status</dt>
                    <dd>
                        @if ($customer->pppAccount?->is_active_session)
                            <span class="badge badge-success"><span class="dot"></span>Connected</span>
                        @else
                            <span class="badge badge-muted">Not connected</span>
                        @endif
                    </dd>
                </div>
                <div class="kv"><dt>Session uptime</dt><dd>{{ $customer->pppAccount?->session_uptime ?? '-' }}</dd></div>
                <div class="kv"><dt>Internet status</dt><dd>{{ $customer->internetStatusLabel() }}</dd></div>
                <div class="kv"><dt>Last status update</dt>
                    <dd>{{ $customer->pppAccount?->last_synced_at?->diffForHumans() ?? 'unknown' }}</dd></div>
            </dl>

            <div class="alert alert-info mt-4">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 flex-none"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 10.6 4 2.4-1 1.6-5-3V6h2v6.6Z"/></svg>
                <span>Connection status is read from the network device. If the network is temporarily unreachable,
                    the last known status is shown and may be slightly out of date.</span>
            </div>
        </x-card>

        <x-card title="Service schedule">
            <dl>
                <div class="kv"><dt>Monthly bill</dt><dd>{{ isp_money($customer->monthly_bill_amount) }}</dd></div>
                <div class="kv"><dt>Next bill generation</dt><dd>{{ $customer->next_bill_date?->format('d M Y') ?? '-' }}</dd></div>
                <div class="kv"><dt>Days until next bill</dt>
                    <dd>
                        @if ($customer->daysUntilNextBill() === null)
                            <span class="badge badge-warning">Date passed</span>
                        @else
                            {{ $customer->daysUntilNextBill() }} day(s)
                        @endif
                    </dd>
                </div>
                <div class="kv"><dt>Scheduled suspension</dt><dd>{{ $customer->next_suspension_date?->format('d M Y') ?? 'not scheduled' }}</dd></div>
                <div class="kv"><dt>Outstanding balance</dt><dd>{{ isp_money($customer->outstandingBalance()) }}</dd></div>
                <div class="kv"><dt>Last suspended</dt><dd>{{ $customer->last_suspended_at?->format('d M Y H:i') ?? 'never' }}</dd></div>
                <div class="kv"><dt>Last restored</dt><dd>{{ $customer->last_restored_at?->format('d M Y H:i') ?? 'never' }}</dd></div>
            </dl>

            @if ($customer->internet_status === 'suspended')
                <div class="alert alert-danger mt-4">
                    <span>Your internet is currently suspended. Settle your outstanding balance to have it restored automatically.</span>
                </div>
                <a href="{{ route('customer.payments.create') }}" class="btn btn-primary mt-3 w-full">Pay outstanding balance</a>
            @endif
        </x-card>
    </div>

    <x-card title="Service history" class="mt-4" :pad="false">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>When</th><th>Action</th><th>Result</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($suspensions as $log)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td><span class="badge badge-muted">{{ ucfirst($log->action) }}</span></td>
                            <td><span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }}">{{ $log->status }}</span></td>
                            <td class="text-sm">{{ $log->message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty title="No service changes recorded" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
