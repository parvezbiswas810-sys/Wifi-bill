@props([
    'name',
    'options' => [],          // [['value' => '', 'label' => '', 'meta' => '', 'disabled' => false]]
    'selected' => null,
    'placeholder' => 'Search...',
    'emptyText' => 'No matches found',
    'required' => false,
])
@php
    $optionsJson = collect($options)->map(fn ($o) => [
        'value' => (string) ($o['value'] ?? ''),
        'label' => (string) ($o['label'] ?? ''),
        'meta' => (string) ($o['meta'] ?? ''),
        'disabled' => (bool) ($o['disabled'] ?? false),
    ])->values();
    $selectedOption = $optionsJson->firstWhere('value', (string) $selected);
@endphp
<div x-data="{
        open: false,
        q: '',
        value: @js((string) ($selected ?? '')),
        label: @js($selectedOption['label'] ?? ''),
        meta: @js($selectedOption['meta'] ?? ''),
        options: @js($optionsJson),
        get filtered() {
            const needle = this.q.trim().toLowerCase();
            if (! needle) return this.options;
            return this.options.filter(o =>
                o.label.toLowerCase().includes(needle) || o.meta.toLowerCase().includes(needle));
        },
        pick(o) {
            if (o.disabled) return;
            this.value = o.value; this.label = o.label; this.meta = o.meta;
            this.open = false; this.q = '';
            this.$dispatch('search-select-changed', { name: @js($name), value: o.value });
        },
        clear() { this.value = ''; this.label = ''; this.meta = ''; this.q = ''; },
     }"
     class="relative" @click.outside="open = false">
    <input type="hidden" name="{{ $name }}" :value="value" @if ($required) required @endif>

    <button type="button" class="input flex items-center justify-between gap-2 text-left"
            @click="open = !open" :aria-expanded="open" aria-haspopup="listbox">
        <span class="min-w-0 truncate">
            <template x-if="label"><span x-text="label"></span></template>
            <template x-if="! label"><span class="text-muted">{{ $placeholder }}</span></template>
        </span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none text-muted"><path d="M5.3 7.3 10 12l4.7-4.7-1.4-1.4L10 9.2 6.7 5.9 5.3 7.3Z"/></svg>
    </button>

    <p class="hint" x-show="meta" x-text="meta"></p>

    <div class="menu-panel w-full" x-show="open" x-cloak style="min-width: 100%">
        <div class="p-1">
            <input type="text" class="input" x-model="q" placeholder="{{ $placeholder }}" autocomplete="off">
        </div>
        <div class="max-h-64 overflow-y-auto scrollbar-thin">
            <template x-for="o in filtered" :key="o.value">
                <button type="button" class="menu-item" :class="o.disabled ? 'opacity-50 cursor-not-allowed' : ''"
                        @click="pick(o)" :disabled="o.disabled">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate" x-text="o.label"></span>
                        <span class="block truncate text-xs text-muted" x-show="o.meta" x-text="o.meta"></span>
                    </span>
                    <svg x-show="value === o.value" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z"/></svg>
                </button>
            </template>
            <p class="px-3 py-3 text-sm text-muted" x-show="filtered.length === 0">{{ $emptyText }}</p>
        </div>
        <div class="divider my-1"></div>
        <button type="button" class="menu-item" @click="clear(); open = false">
            <span class="flex-1">Clear selection</span>
        </button>
    </div>
</div>
