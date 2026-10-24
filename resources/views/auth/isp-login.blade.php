@extends('layouts.auth')

@section('title', 'Administrator sign in')

@section('content')
    <div class="card card-pad">
        <div class="flex items-center gap-2">
            <span class="badge badge-info">ISP Admin</span>
        </div>
        <h2 class="mt-3 text-xl font-bold">Administrator sign in</h2>
        <p class="mt-1 text-sm text-muted">Manage customers, billing and MikroTik routers.</p>

        <form method="POST" action="{{ route('isp.login.attempt') }}" class="mt-6 space-y-4" x-data="{ show: false }">
            @csrf

            <div>
                <label for="username" class="label label-req">Username or email</label>
                <input id="username" name="username" type="text" required autofocus autocomplete="username"
                       value="{{ old('username') }}"
                       class="input @error('username') input-error @enderror"
                       placeholder="admin or admin@example.com">
                @error('username')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="label label-req">Password</label>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" required
                           autocomplete="current-password"
                           class="input @error('password') input-error @enderror pr-11"
                           placeholder="Your password">
                    <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted"
                            :aria-label="show ? 'Hide password' : 'Show password'">
                        <svg x-show="! show" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        <svg x-show="show" x-cloak viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M2 4.3 3.3 3l17.7 17.7-1.3 1.3-3-3A11 11 0 0 1 12 19c-5 0-9-4.5-10-7 .5-1.3 1.9-3.2 3.9-4.6L2 4.3ZM12 7a5 5 0 0 1 5 5c0 .7-.1 1.3-.4 1.9l-2-2A3 3 0 0 0 12 9c-.3 0-.6 0-.9.1l-2-2C10 7.1 11 7 12 7Z"/></svg>
                    </button>
                </div>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="checkbox">
                Keep me signed in
            </label>

            <button type="submit" class="btn btn-primary w-full">Sign in to dashboard</button>
        </form>

        <div class="alert alert-info mt-5">
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 flex-none"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 5a1.25 1.25 0 1 1 0 2.5A1.25 1.25 0 0 1 12 7Zm1 10h-2v-6h2v6Z"/></svg>
            <span>Password resets are handled by the account owner. Contact your system administrator if you are locked out.</span>
        </div>
    </div>

    <p class="mt-5 text-center text-sm text-muted">
        Are you a customer?
        <a href="{{ route('customer.login') }}" class="font-semibold" style="color: var(--color-brand-600)">Go to billing portal</a>
    </p>
@endsection
