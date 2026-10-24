{{--
    Public / authentication layout.
    Used by the customer login page (/) and the ISP administrator login (/loginisp).
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Apply the stored theme before paint to avoid a flash.
        (function () {
            var m = localStorage.getItem('isp-theme') || 'system';
            var d = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (d) document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = d ? 'dark' : 'light';
        })();
    </script>
</head>
<body class="h-full">
<div class="min-h-screen grid lg:grid-cols-2">

    {{-- Brand panel --}}
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden p-10 text-white"
           style="background: linear-gradient(150deg, #0b4171 0%, #005ca6 45%, #0c93f0 100%);">
        <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full" style="background: rgba(255,255,255,.10)"></div>
        <div class="absolute -bottom-28 -left-16 h-80 w-80 rounded-full" style="background: rgba(255,255,255,.07)"></div>

        <div class="relative flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl font-bold"
                  style="background: rgba(255,255,255,.16)">AX</span>
            <div>
                <p class="font-semibold leading-tight">{{ config('app.name') }}</p>
                <p class="text-xs" style="color: rgba(255,255,255,.72)">ISP Billing &amp; MikroTik Management</p>
            </div>
        </div>

        <div class="relative max-w-md">
            <h1 class="text-3xl font-bold leading-tight">Premium ISP billing, built on your real MikroTik routers.</h1>
            <p class="mt-4 text-sm leading-relaxed" style="color: rgba(255,255,255,.82)">
                Automated invoicing, verified payment approval, scheduled suspension and restoration,
                and live PPP synchronization - all in one place.
            </p>
            <ul class="mt-7 space-y-3 text-sm">
                @foreach ([
                    'Automated monthly invoicing per customer',
                    'Payment approval with proof verification',
                    'Automatic suspension and restoration',
                    'Live PPP session synchronization',
                ] as $feature)
                    <li class="flex items-center gap-2.5">
                        <span class="inline-flex h-5 w-5 flex-none items-center justify-center rounded-full"
                              style="background: rgba(255,255,255,.18)">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        </span>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="relative text-xs" style="color: rgba(255,255,255,.6)">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </aside>

    {{-- Form panel --}}
    <main class="flex items-center justify-center px-5 py-10 sm:px-8">
        <div class="w-full max-w-md">
            <div class="mb-7 flex items-center justify-between lg:hidden">
                <div class="flex items-center gap-3">
                    <span class="brand-mark">AX</span>
                    <div>
                        <p class="font-semibold leading-tight">{{ config('app.name') }}</p>
                        <p class="text-xs text-muted">ISP Billing</p>
                    </div>
                </div>
                <x-theme-toggle />
            </div>

            <div class="hidden lg:flex lg:justify-end lg:mb-4">
                <x-theme-toggle />
            </div>

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
