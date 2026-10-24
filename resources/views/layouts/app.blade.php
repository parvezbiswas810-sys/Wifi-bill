{{--
    Admin application shell: fixed sidebar on desktop, drawer on mobile,
    top bar with search, notifications and the three-dot quick menu.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            var m = localStorage.getItem('isp-theme') || 'system';
            var d = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (d) document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = d ? 'dark' : 'light';
        })();
    </script>
</head>
<body class="app-shell" x-data="{ drawer: false, quick: false }">

<div class="flex min-h-screen">

    {{-- ===================== Sidebar ===================== --}}
    <aside class="sidebar fixed inset-y-0 left-0 z-50 w-[17rem] overflow-y-auto scrollbar-thin
                  transition-transform duration-200 lg:translate-x-0 lg:static lg:z-auto"
           :class="drawer ? 'translate-x-0' : '-translate-x-full'">

        <div class="flex items-center gap-3 px-4 py-4">
            <span class="brand-mark">{{ $brandInitials ?? 'AX' }}</span>
            <div class="min-w-0">
                <p class="truncate font-semibold leading-tight">{{ $isp->name ?? config('app.name') }}</p>
                <p class="truncate text-xs text-muted">{{ $isp->currency ?? 'BDT' }} &middot; {{ $isp->timezone ?? 'Asia/Dhaka' }}</p>
            </div>
            <button type="button" class="ml-auto btn btn-ghost btn-icon lg:hidden" @click="drawer = false"
                    aria-label="Close navigation">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M6.3 6.3a1 1 0 0 1 1.4 0L10 8.6l2.3-2.3a1 1 0 1 1 1.4 1.4L11.4 10l2.3 2.3a1 1 0 0 1-1.4 1.4L10 11.4l-2.3 2.3a1 1 0 0 1-1.4-1.4L8.6 10 6.3 7.7a1 1 0 0 1 0-1.4Z"/></svg>
            </button>
        </div>

        <div class="divider mx-4"></div>

        <nav class="px-3 pb-6 pt-2">
            @php
                $sections = [
                    'Overview' => [
                        ['admin.dashboard', 'Dashboard', 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z'],
                    ],
                    'Network' => [
                        ['admin.ppp.index', 'PPP Clients', 'M12 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 12c4.4 0 8 2.2 8 5v3H4v-3c0-2.8 3.6-5 8-5Z'],
                        ['admin.mikrotik.index', 'Routers', 'M4 5h16v4H4V5Zm0 10h16v4H4v-4Zm3-8h.01M7 17h.01'],
                    ],
                    'Billing' => [
                        ['admin.customers.index', 'Customers', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-7 2c-3.9 0-7 1.8-7 4v3h14v-3c0-2.2-3.1-4-7-4Zm7 .5c2.8.3 5 1.8 5 3.5v3h-4v-3c0-1.3-.4-2.5-1-3.5Z'],
                        ['admin.invoices.index', 'Billing History', 'M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM9 12h8v1.5H9V12Zm0 4h8v1.5H9V16Z'],
                        ['admin.payments.index', 'Payments', 'M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7Zm0 3h20v2H2v-2Zm3 5h5v2H5v-2Z'],
                        ['admin.payment-methods.index', 'Payment Methods', 'M4 4h16a1 1 0 0 1 1 1v4H3V5a1 1 0 0 1 1-1Zm-1 7h18v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8Zm4 3h4v2H7v-2Z'],
                    ],
                    'Insights' => [
                        ['admin.reports.index', 'Reports', 'M4 20V10h3v10H4Zm6.5 0V4h3v16h-3ZM17 20v-7h3v7h-3Z'],
                        ['admin.notifications.index', 'Notifications', 'M12 2a6 6 0 0 1 6 6v3.6l1.7 3A1 1 0 0 1 18.8 16H5.2a1 1 0 0 1-.9-1.4l1.7-3V8a6 6 0 0 1 6-6Zm0 20a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3Z'],
                        ['admin.audit.index', 'Audit Log', 'M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM9 13h8v1.5H9V13Zm0 3.5h5V18H9v-1.5Z'],
                    ],
                    'System' => [
                        ['admin.settings.index', 'Settings', 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm9 4a9 9 0 0 0-.1-1.3l2-1.5-2-3.4-2.4 1a9 9 0 0 0-2.2-1.3L15.8 3h-4l-.5 2.5a9 9 0 0 0-2.2 1.3l-2.4-1-2 3.4 2 1.5a9 9 0 0 0 0 2.6l-2 1.5 2 3.4 2.4-1a9 9 0 0 0 2.2 1.3l.5 2.5h4l.5-2.5a9 9 0 0 0 2.2-1.3l2.4 1 2-3.4-2-1.5c.1-.4.1-.9.1-1.3Z'],
                    ],
                ];
            @endphp

            @foreach ($sections as $label => $links)
                <p class="nav-group-label">{{ $label }}</p>
                @foreach ($links as [$route, $text, $icon])
                    <a href="{{ Route::has($route) ? route($route) : '#' }}"
                       class="nav-link {{ request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)) ? 'active' : '' }}"
                       @if (! Route::has($route)) aria-disabled="true" @endif>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="{{ $icon }}"/></svg>
                        <span>{{ $text }}</span>
                        @if ($text === 'Payments' && ($pendingPaymentsCount ?? 0) > 0)
                            <span class="badge badge-warning ml-auto">{{ $pendingPaymentsCount }}</span>
                        @endif
                        @if ($text === 'Notifications' && ($unreadNotificationsCount ?? 0) > 0)
                            <span class="badge badge-info ml-auto">{{ $unreadNotificationsCount }}</span>
                        @endif
                    </a>
                @endforeach
            @endforeach

            <div class="divider my-4"></div>

            <form method="POST" action="{{ route('isp.logout') }}">
                @csrf
                <button type="submit" class="nav-link w-full text-left">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3Zm6.2 3.4L21 12l-4.8 5.6-1.5-1.3 2.4-2.8H9v-2h8.1l-2.4-2.8 1.5-1.3Z"/></svg>
                    <span>Logout</span>
                </button>
            </form>
        </nav>
    </aside>

    {{-- Mobile overlay --}}
    <div class="fixed inset-0 z-40 bg-black/40 lg:hidden" x-show="drawer" x-cloak @click="drawer = false"></div>

    {{-- ===================== Main column ===================== --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-app surface">
            <div class="flex items-center gap-3 px-4 py-3 sm:px-6">
                <button type="button" class="btn btn-secondary btn-icon lg:hidden" @click="drawer = true"
                        aria-label="Open navigation">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z"/></svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate page-title">@yield('title', 'Dashboard')</h1>
                    @hasSection('subtitle')
                        <p class="truncate page-subtitle">@yield('subtitle')</p>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @yield('header-actions')

                    <x-theme-toggle />

                    {{-- Notifications --}}
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary btn-icon relative"
                       aria-label="Notifications">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 2a6 6 0 0 1 6 6v3.6l1.7 3A1 1 0 0 1 18.8 16H5.2a1 1 0 0 1-.9-1.4l1.7-3V8a6 6 0 0 1 6-6Zm0 20a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3Z"/></svg>
                        @if (($unreadNotificationsCount ?? 0) > 0)
                            <span class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                                {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                            </span>
                        @endif
                    </a>

                    {{-- Three-dot quick menu --}}
                    <div class="relative" @click.outside="quick = false">
                        <button type="button" class="three-dot-btn" @click="quick = !quick"
                                :aria-expanded="quick" aria-haspopup="true" aria-label="Quick menu">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 5.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z"/></svg>
                        </button>

                        <div class="menu-panel" x-show="quick" x-cloak @click="quick = false">
                            <div class="px-2.5 py-2">
                                <p class="text-sm font-semibold">{{ auth('isp')->user()->name ?? '' }}</p>
                                <p class="text-xs text-muted">{{ auth('isp')->user()->email ?? '' }}</p>
                            </div>
                            <div class="divider my-1"></div>

                            <a href="{{ route('admin.dashboard') }}" class="menu-item">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
                                Dashboard
                            </a>
                            <a href="{{ route('admin.customers.create') }}" class="menu-item">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/></svg>
                                Add customer
                            </a>
                            <a href="{{ route('admin.mikrotik.index') }}" class="menu-item">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 5h16v4H4V5Zm0 10h16v4H4v-4Z"/></svg>
                                MikroTik routers
                            </a>
                            <a href="{{ route('admin.payments.index') }}" class="menu-item">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7Z"/></svg>
                                Pending payments
                                @if (($pendingPaymentsCount ?? 0) > 0)
                                    <span class="badge badge-warning ml-auto">{{ $pendingPaymentsCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('admin.reports.index') }}" class="menu-item">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 20V10h3v10H4Zm6.5 0V4h3v16h-3ZM17 20v-7h3v7h-3Z"/></svg>
                                Reports
                            </a>
                            <a href="{{ route('admin.settings.index') }}" class="menu-item">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
                                Settings
                            </a>

                            <div class="divider my-1"></div>

                            <form method="POST" action="{{ route('isp.logout') }}">
                                @csrf
                                <button type="submit" class="menu-item">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3Z"/></svg>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Page body --}}
        <main class="flex-1 px-4 py-5 sm:px-6 sm:py-6">
            <x-flash />

            @if (! empty($mikrotikAlert))
                <div class="alert alert-danger mb-5">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 flex-none"><path d="M12 2 1 21h22L12 2Zm0 6 1 8h-2l1-8Zm0 11a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>
                    <div>
                        <p class="font-semibold">Router problem detected</p>
                        <p>{{ $mikrotikAlert }}</p>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-app px-4 py-4 text-xs text-muted sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>{{ config('app.name') }} &middot; {{ $isp->name ?? '' }}</span>
                <span>Last router sync: {{ optional($isp?->routers()->max('last_synced_at') ? \Carbon\Carbon::parse($isp->routers()->max('last_synced_at')) : null)?->diffForHumans() ?? 'never' }}</span>
            </div>
        </footer>
    </div>
</div>

<div id="toast-stack" class="toast-stack"></div>
@stack('scripts')
</body>
</html>
