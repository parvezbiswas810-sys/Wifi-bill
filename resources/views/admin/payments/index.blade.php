@extends('layouts.app')

@section('title', 'Payment approvals')
@section('subtitle', 'Verify customer payment submissions before crediting them')

@section('content')
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Pending" :value="number_format($counts['pending'])"
                     :tone="$counts['pending'] > 0 ? 'warning' : 'brand'"
                     :hint="isp_money($counts['pending_amount']).' awaiting verification'" />
        <x-stat-card label="Approved" :value="number_format($counts['approved'])" tone="success" />
        <x-stat-card label="Rejected" :value="number_format($counts['rejected'])" tone="danger" />
        <x-stat-card label="Info requested" :value="number_format($counts['info_requested'])" />
    </div>

    <div class="card mt-4">
        <div class="card-head">
            <div class="flex flex-wrap gap-1 rounded-xl p-1 surface-muted">
                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'info_requested' => 'Info requested', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.payments.index', array_filter(['status' => $key, 'q' => request('q')])) }}"
                       class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" class="card-head gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" class="input flex-1 sm:max-w-xs"
                   placeholder="Reference, sender number or customer">
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                @if (request('q'))
                    <a href="{{ route('admin.payments.index', ['status' => $status]) }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>

        <div class="divide-y border-app border-t">
            @forelse ($submissions as $submission)
                <div class="card-pad" x-data="{ reject: false, info: false }">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $submission->customer?->name }}</p>
                                <span class="badge {{ match($submission->status) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'info_requested' => 'badge-warning',
                                    default => 'badge-info',
                                } }}">{{ $submission->statusLabel() }}</span>
                                <span class="text-xs text-muted">{{ $submission->customer?->customer_code }}</span>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
                                <div>
                                    <p class="text-xs text-muted">Invoice</p>
                                    <p class="font-medium">{{ $submission->invoice?->invoice_number ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Method</p>
                                    <p class="font-medium">{{ $submission->method?->name ?? $submission->method_name ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Submitted amount</p>
                                    <p class="font-semibold">{{ isp_money($submission->amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Invoice balance</p>
                                    <p class="font-medium">{{ isp_money($submission->invoice?->balance ?? 0) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Sender number</p>
                                    <p class="font-medium">{{ $submission->sender_number ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Transaction reference</p>
                                    <p class="font-medium break-all">{{ $submission->reference ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Submitted</p>
                                    <p class="font-medium">{{ $submission->created_at->format('d M Y H:i') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted">Customer phone</p>
                                    <p class="font-medium">{{ $submission->customer?->phone ?? '-' }}</p>
                                </div>
                            </div>

                            @if ($submission->screenshot_path)
                                <a href="{{ route('admin.payments.screenshot', $submission) }}" target="_blank"
                                   class="btn btn-secondary btn-sm mt-3">View payment proof</a>
                            @endif

                            @if ($submission->rejection_reason)
                                <div class="alert alert-danger mt-3">
                                    <span>Rejected: {{ $submission->rejection_reason }}</span>
                                </div>
                            @endif
                            @if ($submission->admin_note)
                                <div class="alert alert-info mt-3">
                                    <span>Admin note: {{ $submission->admin_note }}</span>
                                </div>
                            @endif
                            @if ($submission->reviewed_at)
                                <p class="mt-2 text-xs text-muted">
                                    Reviewed by {{ $submission->reviewer?->name ?? 'system' }}
                                    on {{ $submission->reviewed_at->format('d M Y H:i') }}
                                </p>
                            @endif
                        </div>

                        {{-- Actions --}}
                        @if ($submission->status === \App\Models\PaymentSubmission::STATUS_PENDING
                             || $submission->status === \App\Models\PaymentSubmission::STATUS_INFO_REQUESTED)
                            <div class="w-full space-y-2 sm:w-56">
                                <form method="POST" action="{{ route('admin.payments.approve', $submission) }}" class="space-y-2">
                                    @csrf
                                    <div>
                                        <label class="label" for="amount-{{ $submission->id }}">Approve amount</label>
                                        <input id="amount-{{ $submission->id }}" name="amount" type="number" step="0.01"
                                               min="0.01" max="{{ $submission->invoice?->balance ?? $submission->amount }}"
                                               value="{{ $submission->amount }}" class="input">
                                    </div>
                                    <input type="hidden" name="note" value="Approved from payment approvals">
                                    <button type="submit" class="btn btn-success w-full">Approve</button>
                                </form>

                                <button type="button" class="btn btn-danger w-full" @click="reject = true">Reject</button>
                                <button type="button" class="btn btn-secondary w-full" @click="info = true">Request info</button>
                            </div>
                        @endif
                    </div>

                    {{-- Reject modal --}}
                    <template x-teleport="body">
                        <div class="modal-backdrop" x-show="reject" x-cloak @keydown.escape.window="reject = false">
                            <div class="modal-panel card-pad" @click.outside="reject = false">
                                <p class="font-semibold">Reject this submission</p>
                                <p class="mt-1 text-sm text-muted">A reason is required and is shown to the customer.</p>
                                <form method="POST" action="{{ route('admin.payments.reject', $submission) }}" class="mt-4">
                                    @csrf
                                    <label class="label label-req" for="reason-{{ $submission->id }}">Rejection reason</label>
                                    <textarea id="reason-{{ $submission->id }}" name="rejection_reason" rows="3" required
                                              class="textarea" placeholder="e.g. transaction ID not found"></textarea>
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button type="button" class="btn btn-secondary" @click="reject = false">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Reject submission</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>

                    {{-- Request info modal --}}
                    <template x-teleport="body">
                        <div class="modal-backdrop" x-show="info" x-cloak @keydown.escape.window="info = false">
                            <div class="modal-panel card-pad" @click.outside="info = false">
                                <p class="font-semibold">Request more information</p>
                                <form method="POST" action="{{ route('admin.payments.request-info', $submission) }}" class="mt-4">
                                    @csrf
                                    <label class="label label-req" for="note-{{ $submission->id }}">Message to customer</label>
                                    <textarea id="note-{{ $submission->id }}" name="admin_note" rows="3" required
                                              class="textarea" placeholder="What do you need from the customer?"></textarea>
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button type="button" class="btn btn-secondary" @click="info = false">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Send request</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>
            @empty
                <x-empty title="No submissions here"
                    message="Customer payment submissions will appear here for verification." />
            @endforelse
        </div>

        @if ($submissions->hasPages())
            <div class="card-pad border-t border-app">{{ $submissions->links() }}</div>
        @endif
    </div>
@endsection
