@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand'])
@php
    $toneClass = match ($tone) {
        'success' => 'badge-success',
        'danger' => 'badge-danger',
        'warning' => 'badge-warning',
        default => 'badge-info',
    };
@endphp
<div class="stat-card">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="stat-label">{{ $label }}</p>
            <p class="stat-value">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <span class="stat-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="{{ $icon }}"/></svg>
            </span>
        @endif
    </div>
</div>
