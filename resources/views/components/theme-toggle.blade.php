@props(['align' => 'right'])
<div class="relative" x-data="{ open: false, mode: (localStorage.getItem('isp-theme') || 'system') }" @click.outside="open = false">
    <button type="button" class="btn btn-secondary btn-icon" @click="open = !open" aria-label="Change theme" aria-haspopup="true">
        <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0-15a1 1 0 0 1 1 1v1.5a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 15a1 1 0 0 1 1 1v1.5a1 1 0 1 1-2 0V19a1 1 0 0 1 1-1ZM3 12a1 1 0 0 1 1-1h1.5a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm15 0a1 1 0 0 1 1-1h1.5a1 1 0 1 1 0 2H19a1 1 0 0 1-1-1ZM5.6 5.6a1 1 0 0 1 1.4 0l1 1a1 1 0 0 1-1.4 1.4l-1-1a1 1 0 0 1 0-1.4Zm10.4 10.4a1 1 0 0 1 1.4 0l1 1a1 1 0 0 1-1.4 1.4l-1-1a1 1 0 0 1 0-1.4Zm2.4-10.4a1 1 0 0 1 0 1.4l-1 1A1 1 0 0 1 16 6.6l1-1a1 1 0 0 1 1.4 0ZM7 17l-1 1a1 1 0 0 1-1.4-1.4l1-1A1 1 0 0 1 7 17Z"/></svg>
    </button>
    <div class="menu-panel" x-show="open" x-cloak>
        <template x-for="opt in [['light','Light'],['dark','Dark'],['system','System']]" :key="opt[0]">
            <button type="button" class="menu-item" @click="theme.set(opt[0]); mode = opt[0]; open = false">
                <span class="flex-1" x-text="opt[1]"></span>
                <svg x-show="mode === opt[0]" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z"/></svg>
            </button>
        </template>
    </div>
</div>
