@extends('layouts.app')

@section('title', 'Audit log')
@section('subtitle', 'Every financial and configuration change, with the responsible actor')

@section('content')
    <div class="card">
        <form method="GET" class="card-head gap-3">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ request('q') }}" class="input sm:max-w-xs"
                       placeholder="Search action or description">
                <select name="action" class="select sm:max-w-[16rem]">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                @if (request()->hasAny(['q', 'action']))
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>When</th><th>Action</th><th>Description</th><th>Actor</th><th>Subject</th><th>Changes</th><th>IP</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td><span class="badge badge-muted">{{ $log->action }}</span></td>
                            <td class="text-sm">{{ $log->description }}</td>
                            <td class="text-sm">{{ ucfirst($log->actor_type) }} #{{ $log->actor_id ?? '-' }}</td>
                            <td class="text-xs text-muted">
                                @if ($log->auditable_type)
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-xs">
                                @if ($log->new_values)
                                    <details>
                                        <summary class="cursor-pointer" style="color: var(--color-brand-600)">View</summary>
                                        <pre class="mt-1 max-w-xs overflow-x-auto rounded-lg p-2 text-[11px] surface-muted">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-xs text-muted">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No audit entries" message="Financial and configuration changes will be recorded here." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="card-pad border-t border-app">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
