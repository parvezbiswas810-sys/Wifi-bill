@props(['title' => 'Nothing here yet', 'message' => null, 'icon' => 'M4 4h16v4H4V4Zm0 6h16v10H4V10Z'])
<div class="empty-state">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="{{ $icon }}"/></svg>
    <p class="font-semibold text-base" style="color: var(--text)">{{ $title }}</p>
    @if ($message)
        <p class="max-w-md text-sm">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
