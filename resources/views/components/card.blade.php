@props(['title' => null, 'subtitle' => null, 'pad' => true])
<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title)
        <div class="card-head">
            <div class="min-w-0">
                <p class="font-semibold">{{ $title }}</p>
                @if ($subtitle)
                    <p class="text-xs text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="{{ $pad ? 'card-pad' : '' }}">{{ $slot }}</div>
</div>
