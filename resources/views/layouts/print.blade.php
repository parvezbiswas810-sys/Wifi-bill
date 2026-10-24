{{-- Standalone layout for printable invoices. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Invoice')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="app-shell">
    <div class="mx-auto max-w-3xl p-4 sm:p-8">
        <div class="mb-4 flex justify-end gap-2 no-print">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            <a href="@yield('back_url', '#')" class="btn btn-secondary">Back</a>
        </div>
        @yield('content')
    </div>
</body>
</html>
