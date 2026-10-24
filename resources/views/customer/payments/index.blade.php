@extends('layouts.customer')

@section('title', 'Payment history')
@section('subtitle', 'Your submitted and approved payments')

@section('header-actions')
    <a href="{{ route('customer.payments.create') }}" class="btn btn-primary btn-sm">Make payment</a>
@endsection

@section('content')
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Submitted</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td class="whitespace-nowrap text-sm">{{ $submission->created_at->format('d M Y H:i') }}</td>
                            <td class="text-sm">{{ $submission->invoice?->invoice_number ?? '-' }}</td>
                            <td class="text-sm">{{ $submission->method?->name ?? $submission->method_name ?? '-' }}</td>
                            <td class="text-sm">{{ $submission->reference ?? '-' }}</td>
                            <td class="font-semibold">{{ isp_money($submission->amount) }}</td>
                            <td>
                                <span class="badge {{ match($submission->status) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'info_requested' => 'badge-warning',
                                    default => 'badge-info',
                                } }}">{{ $submission->statusLabel() }}</span>
                                @if ($submission->rejection_reason)
                                    <p class="mt-1 text-xs text-muted">{{ $submission->rejection_reason }}</p>
                                @endif
                                @if ($submission->admin_note && $submission->status === 'info_requested')
                                    <p class="mt-1 text-xs text-muted">{{ $submission->admin_note }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No payments submitted yet"
                            message="Once you submit a payment it will appear here with its verification status.">
                            <a href="{{ route('customer.payments.create') }}" class="btn btn-primary btn-sm mt-2">Make payment</a>
                        </x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($submissions->hasPages())
            <div class="card-pad border-t border-app">{{ $submissions->links() }}</div>
        @endif
    </div>
@endsection
