@props(['title' => 'Are you sure?', 'message' => 'This action cannot be undone.', 'confirmLabel' => 'Confirm', 'tone' => 'danger'])
<div x-data="{ open: false }" class="inline-flex">
    <span @click="open = true">{{ $trigger ?? '' }}</span>
    <template x-teleport="body">
        <div class="modal-backdrop" x-show="open" x-cloak @keydown.escape.window="open = false">
            <div class="modal-panel card-pad" @click.outside="open = false">
                <div class="flex items-start gap-3">
                    <span class="stat-icon" style="background:#fef2f2;color:#b91c1c">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 2 1 21h22L12 2Zm0 6 1 8h-2l1-8Zm0 11a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>
                    </span>
                    <div>
                        <p class="font-semibold">{{ $title }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $message }}</p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                    <button type="button" class="btn btn-{{ $tone }}" @click="open = false; $dispatch('confirmed')">{{ $confirmLabel }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
