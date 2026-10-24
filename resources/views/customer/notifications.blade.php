@extends('layouts.customer')

@section('title', 'Notifications')
@section('subtitle', 'Updates about your billing and connection')

@section('content')
    <div class="card">
        <div class="divide-y border-app border-t">
            @forelse ($notifications as $notification)
                <div class="flex items-start gap-3 px-4 py-3">
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
                </div>
            @empty
                <x-empty title="No notifications yet"
                    message="You will be notified about new invoices, payments and service changes." />
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="card-pad border-t border-app">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
