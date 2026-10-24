@extends('layouts.app')

@section('title', 'Synchronization logs')
@section('subtitle', 'Every router connection, synchronization and suspension attempt')

@section('content')
    <div class="card">
        <form method="GET" class="card-head gap-3">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <select name="type" class="select sm:max-w-[13rem]">
                    <option value="">All operations</option>
                    @foreach (['connection_test', 'ppp_sync', 'suspend', 'restore'] as $t)
                        <option value="{{ $t }}" @selected(request('type') === $t)>{{ str_replace('_', ' ', ucfirst($t)) }}</option>
                    @endforeach
                </select>
                <select name="status" class="select sm:max-w-[10rem]">
                    <option value="">All results</option>
                    <option value="success" @selected(request('status') === 'success')>Success</option>
                    <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                @if (request()->hasAny(['type', 'status']))
                    <a href="{{ route('admin.mikrotik.logs') }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>When</th><th>Router</th><th>Operation</th><th>Result</th><th>Details</th><th>Duration</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td class="text-sm">{{ $log->router?->name ?? '-' }}</td>
                            <td><span class="badge badge-muted">{{ $log->type }}</span></td>
                            <td><span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }}">{{ $log->status }}</span></td>
                            <td class="text-sm">{{ Str::limit($log->message, 120) }}</td>
                            <td class="text-xs text-muted">{{ $log->duration_ms ? $log->duration_ms.' ms' : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No log entries" message="Router activity will appear here." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="card-pad border-t border-app">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
