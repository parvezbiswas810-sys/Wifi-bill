{{-- Customer portal shell: mobile-first, branded, no router internals exposed. --}}
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
<body class="app-shell" x-data="{ menu: false }">

<header class="sticky top-0 z-30 border-b border-app surface">
    <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
        <span class="brand-mark">{{ $brandInitials ?? 'AX' }}</span>
        <div class="min-w-0 flex-1">
            <p class="truncate font-semibold leading-tight">{{ $isp->name ?? config('app.name') }}</p>
            <p class="truncate text-xs text-muted">{{ auth('customer')->user()->name }} &middot; {{ auth('customer')->user()->customer_code }}</p>
        </div>

        <x-theme-toggle />

        <div class="relative" @click.outside="menu = false">
            <button type="button" class="three-dot-btn" @click="menu = ! menu" aria-label="Open menu" aria-haspopup="true">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 5.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm0 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z"/></svg>
            </button>
            <div class="menu-panel" x-show="menu" x-cloak @click="menu = false">
                @foreach ([
                    ['customer.dashboard', 'Dashboard'],
                    ['customer.invoices.index', 'My Invoices'],
                    ['customer.payments.index', 'Payment History'],
                    ['customer.payments.create', 'Make Payment'],
                    ['customer.connection', 'My Connection'],
                    ['customer.profile', 'My Profile'],
                    ['customer.notifications', 'Notifications'],
                ] as [$route, $label])
                    <a href="{{ route($route) }}" class="menu-item {{ request()->routeIs($route) ? 'font-semibold' : '' }}">{{ $label }}</a>
                @endforeach
                <div class="divider my-1"></div>
                @if ($isp?->contact_number)
                    <a href="tel:{{ $isp->contact_number }}" class="menu-item">Support: {{ $isp->contact_number }}</a>
                @endif
                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="menu-item w-full">Logout</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Desktop nav --}}
    <nav class="mx-auto hidden max-w-5xl gap-1 px-4 pb-2 md:flex">
        @foreach ([
            ['customer.dashboard', 'Dashboard'],
            ['customer.invoices.index', 'My Invoices'],
            ['customer.payments.index', 'Payment History'],
            ['customer.payments.create', 'Make Payment'],
            ['customer.connection', 'My Connection'],
            ['customer.profile', 'My Profile'],
        ] as [$route, $label])
            <a href="{{ route($route) }}"
               class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
</header>

<main class="mx-auto max-w-5xl px-4 py-5">
    <x-flash />

    <div class="mb-4">
        <h1 class="page-title">@yield('title', 'Dashboard')</h1>
        @hasSection('subtitle')<p class="page-subtitle">@yield('subtitle')</p>@endif
    </div>

    @yield('content')
</main>

<footer class="mx-auto max-w-5xl px-4 pb-8 pt-2 text-center text-xs text-muted">
    <p>{{ $isp->name ?? config('app.name') }}</p>
    @if ($isp?->contact_number || $isp?->email)
        <p class="mt-1">
            Support:
            @if ($isp?->contact_number) {{ $isp->contact_number }} @endif
            @if ($isp?->contact_number && $isp?->email) &middot; @endif
            @if ($isp?->email) {{ $isp->email }} @endif
        </p>
    @endif
</footer>

<div id="toast-stack" class="toast-stack"></div>
@stack('scripts')
</body>
</html>
