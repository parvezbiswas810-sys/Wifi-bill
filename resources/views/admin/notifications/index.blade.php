@extends('layouts.app')

@section('title', 'Notifications')
@section('subtitle', 'Invoices, payments, suspensions and router events')

@section('header-actions')
    <form method="POST" action="{{ route('admin.notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm">Mark all as read</button>
    </form>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <div class="flex gap-1 rounded-xl p-1 surface-muted">
                @foreach (['unread' => 'Unread', 'read' => 'Read', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.notifications.index', ['filter' => $key]) }}"
                       class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-ghost' }}">
                        {{ $label }}
                        @if ($key === 'unread' && $counts['unread'] > 0)
                            <span class="badge badge-warning">{{ $counts['unread'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="divide-y border-app border-t">
            @forelse ($notifications as $notification)
                <div class="flex items-start gap-3 px-4 py-3 {{ $notification->read_at ? '' : 'surface-muted' }}">
                    <span class="badge flex-none {{ match($notification->severity) {
                        'success' => 'badge-success',
                        'warning' => 'badge-warning',
                        'danger' => 'badge-danger',
                        default => 'badge-info',
                    } }}">{{ str_replace('_', ' ', $notification->type) }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $notification->title }}</p>
                        <p class="text-sm text-muted">{{ $notification->body }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>

                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('admin.notifications.read', $notification) }}" class="flex-none">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">Mark read</button>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty title="No notifications"
                    message="Invoice, payment and router events will appear here." />
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="card-pad border-t border-app">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
