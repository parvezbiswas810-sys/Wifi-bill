@extends('layouts.app')

@section('title', 'Reports')
@section('subtitle', 'Filter, review and export your billing and network data')

@section('content')
    @php
        $reports = [
            'daily_collection' => 'Daily collection',
            'monthly_collection' => 'Monthly collection',
            'outstanding' => 'Outstanding invoices',
            'overdue_customers' => 'Overdue customers',
            'active_ppp' => 'Active PPP sessions',
            'inactive_ppp' => 'Inactive PPP accounts',
            'suspended_customers' => 'Suspended customers',
            'payment_approvals' => 'Payment approvals',
            'invoice_failures' => 'Invoice generation failures',
            'mikrotik_failures' => 'MikroTik operation failures',
        ];
        $needsDates = in_array($type, ['daily_collection', 'monthly_collection', 'payment_approvals', 'mikrotik_failures'], true);
    @endphp

    <div class="card">
        <form method="GET" class="card-head gap-3">
            <div class="flex flex-1 flex-wrap items-end gap-3">
                <div>
                    <label for="type" class="label">Report</label>
                    <select id="type" name="type" class="select sm:min-w-[15rem]">
                        @foreach ($reports as $key => $label)
                            <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from" class="label">From</label>
                    <input id="from" name="from" type="date" value="{{ $from->format('Y-m-d') }}" class="input">
                </div>
                <div>
                    <label for="to" class="label">To</label>
                    <input id="to" name="to" type="date" value="{{ $to->format('Y-m-d') }}" class="input">
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Run report</button>
                <a href="{{ route('admin.reports.export', request()->only(['type', 'from', 'to'])) }}"
                   class="btn btn-secondary btn-sm">Export CSV</a>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print</button>
            </div>
        </form>

        <div class="card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="font-semibold">{{ $title }}</p>
                    <p class="text-xs text-muted">
                        @if ($needsDates)
                            {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}
                        @else
                            Current state
                        @endif
                    </p>
                </div>
                @if (! empty($totals))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($totals as $label => $value)
                            <span class="badge badge-info">{{ $label }}: {{ $value }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (count($rows) > 0)
                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead>
                            <tr>
                                @foreach ($columns as $key => $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach (array_keys($columns) as $key)
                                        <td class="text-sm">{{ $row[$key] ?? '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-muted">{{ count($rows) }} row(s)</p>
            @else
                <x-empty title="No data for this report"
                    message="Try a different date range, or select another report." />
            @endif
        </div>
    </div>
@endsection
