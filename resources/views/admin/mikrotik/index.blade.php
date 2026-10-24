@extends('layouts.app')

@section('title', 'MikroTik routers')
@section('subtitle', 'Connect real RouterOS devices and synchronize PPP accounts')

@section('header-actions')
    <a href="{{ route('admin.mikrotik.logs') }}" class="btn btn-secondary btn-sm">Synchronization logs</a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {{-- ============ Router list ============ --}}
        <div class="space-y-4 xl:col-span-2">
            @forelse ($routers as $router)
                <div class="card" x-data="{ edit: false }">
                    <div class="card-head">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-semibold">{{ $router->name }}</p>
                                <span class="badge {{ $router->isOnline() ? 'badge-success' : ($router->last_status === 'unknown' ? 'badge-muted' : 'badge-danger') }}">
                                    <span class="dot"></span>{{ ucfirst($router->last_status) }}
                                </span>
                                @if (! $router->is_enabled)
                                    <span class="badge badge-muted">Disabled</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ $router->host }}:{{ $router->api_port }}
                                &middot; {{ $router->use_ssl ? 'API-SSL' : 'API' }}
                                &middot; user {{ $router->api_username }}
                            </p>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" @click="edit = ! edit">Edit</button>
                    </div>

                    <div class="card-pad">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <p class="stat-label">PPP accounts</p>
                                <p class="mt-1 font-semibold">{{ number_format($router->ppp_accounts_count) }}</p>
                            </div>
                            <div>
                                <p class="stat-label">Customers</p>
                                <p class="mt-1 font-semibold">{{ number_format($router->customers_count) }}</p>
                            </div>
                            <div>
                                <p class="stat-label">Last checked</p>
                                <p class="mt-1 text-sm">{{ $router->last_checked_at?->diffForHumans() ?? 'never' }}</p>
                            </div>
                            <div>
                                <p class="stat-label">Last synced</p>
                                <p class="mt-1 text-sm">{{ $router->last_synced_at?->diffForHumans() ?? 'never' }}</p>
                            </div>
                        </div>

                        @if ($router->last_error)
                            <div class="alert alert-danger mt-4">
                                <span class="break-all">{{ $router->last_error }}</span>
                            </div>
                        @endif

                        @if ($router->notes)
                            <p class="mt-3 text-sm text-muted">{{ $router->notes }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2" x-data="{ busy: '' }">
                            <button type="button" class="btn btn-secondary btn-sm" :disabled="busy !== ''"
                                    @click="
                                        busy = 'test';
                                        window.apiFetch('{{ route('admin.mikrotik.test', $router) }}', { method: 'POST' })
                                            .then(r => window.toast(r.message + (r.version ? ' (RouterOS ' + r.version + ')' : ''), 'success'))
                                            .catch(e => window.toast(e.message, 'error'))
                                            .finally(() => busy = '');
                                    ">
                                <span x-show="busy !== 'test'">Test connection</span>
                                <span x-show="busy === 'test'" x-cloak>Testing...</span>
                            </button>

                            <button type="button" class="btn btn-primary btn-sm" :disabled="busy !== ''"
                                    @click="
                                        busy = 'sync';
                                        window.apiFetch('{{ route('admin.mikrotik.sync', $router) }}', { method: 'POST' })
                                            .then(r => { window.toast(r.message, 'success'); setTimeout(() => location.reload(), 900); })
                                            .catch(e => window.toast(e.message, 'error'))
                                            .finally(() => busy = '');
                                    ">
                                <span x-show="busy !== 'sync'">Sync PPP clients</span>
                                <span x-show="busy === 'sync'" x-cloak>Syncing...</span>
                            </button>

                            <button type="button" class="btn btn-ghost btn-sm" :disabled="busy !== ''"
                                    @click="
                                        busy = 'disc';
                                        window.apiFetch('{{ route('admin.mikrotik.disconnect', $router) }}', { method: 'POST' })
                                            .then(r => window.toast(r.message, 'info'))
                                            .finally(() => busy = '');
                                    ">
                                Disconnect
                            </button>

                            <a href="{{ route('admin.mikrotik.logs') }}" class="btn btn-ghost btn-sm">View logs</a>
                        </div>
                    </div>

                    {{-- Inline edit form --}}
                    <div class="border-t border-app" x-show="edit" x-cloak>
                        <form method="POST" action="{{ route('admin.mikrotik.update', $router) }}" class="card-pad">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label label-req">Router name</label>
                                    <input name="name" type="text" required value="{{ $router->name }}" class="input">
                                </div>
                                <div>
                                    <label class="label label-req">Host or IP address</label>
                                    <input name="host" type="text" required value="{{ $router->host }}" class="input">
                                </div>
                                <div>
                                    <label class="label label-req">API port</label>
                                    <input name="api_port" type="number" min="1" max="65535" required value="{{ $router->api_port }}" class="input">
                                </div>
                                <div>
                                    <label class="label label-req">Connection mode</label>
                                    <select name="connection_mode" class="select">
                                        <option value="api" @selected($router->connection_mode === 'api')>API (plain, port 8728)</option>
                                        <option value="api-ssl" @selected($router->connection_mode === 'api-ssl')>API-SSL (port 8729)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label label-req">API username</label>
                                    <input name="api_username" type="text" required value="{{ $router->api_username }}" class="input">
                                </div>
                                <div>
                                    <label class="label">API password</label>
                                    <input name="api_password" type="password" class="input"
                                           placeholder="Leave blank to keep the stored password" autocomplete="new-password">
                                    <p class="hint">Stored encrypted. It is never displayed again.</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Notes</label>
                                    <textarea name="notes" rows="2" class="textarea">{{ $router->notes }}</textarea>
                                </div>
                                <div class="sm:col-span-2 space-y-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="use_ssl" value="1" class="checkbox" @checked($router->use_ssl)>
                                        Use TLS for the API connection
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="is_enabled" value="1" class="checkbox" @checked($router->is_enabled)>
                                        Router enabled for synchronization
                                    </label>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" @click="edit = false">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm">Save router</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.mikrotik.destroy', $router) }}"
                              class="card-pad pt-0"
                              onsubmit="return confirm('Remove this router? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete router</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="card">
                    <x-empty title="No MikroTik router configured"
                        message="Add your RouterOS device to synchronize PPP accounts and control internet access." />
                </div>
            @endforelse
        </div>

        {{-- ============ Add router ============ --}}
        <div class="space-y-4">
            <x-card title="Add a router" subtitle="RouterOS API credentials">
                <form method="POST" action="{{ route('admin.mikrotik.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="label label-req">Router name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name') }}" class="input"
                               placeholder="e.g. Main Tower">
                        @error('name')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="host" class="label label-req">IP address or hostname</label>
                        <input id="host" name="host" type="text" required value="{{ old('host') }}" class="input"
                               placeholder="e.g. 103.x.x.x or router.example.com">
                        @error('host')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="api_port" class="label label-req">API port</label>
                            <input id="api_port" name="api_port" type="number" min="1" max="65535" required
                                   value="{{ old('api_port', 8728) }}" class="input">
                            @error('api_port')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="connection_mode" class="label label-req">Mode</label>
                            <select id="connection_mode" name="connection_mode" class="select">
                                <option value="api" @selected(old('connection_mode') === 'api')>API</option>
                                <option value="api-ssl" @selected(old('connection_mode') === 'api-ssl')>API-SSL</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="api_username" class="label label-req">API username</label>
                        <input id="api_username" name="api_username" type="text" required
                               value="{{ old('api_username') }}" class="input" autocomplete="off">
                        @error('api_username')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="api_password" class="label label-req">API password</label>
                        <input id="api_password" name="api_password" type="password" required class="input"
                               autocomplete="new-password">
                        <p class="hint">Encrypted at rest and never shown in the browser.</p>
                        @error('api_password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="notes" class="label">Notes</label>
                        <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes') }}</textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="use_ssl" value="1" class="checkbox" @checked(old('use_ssl'))>
                        Use TLS for the API connection
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_enabled" value="1" class="checkbox" checked>
                        Enable this router
                    </label>

                    <button type="submit" class="btn btn-primary w-full">Save router</button>
                </form>
            </x-card>

            <div class="card card-pad">
                <p class="font-semibold">Router prerequisites</p>
                <ul class="mt-3 space-y-2 text-sm text-muted">
                    <li>1. Enable the API service on the router: <code class="rounded px-1 surface-muted">/ip service enable api</code></li>
                    <li>2. For TLS, enable <code class="rounded px-1 surface-muted">api-ssl</code> instead and use port 8729.</li>
                    <li>3. Create a dedicated user with the <code class="rounded px-1 surface-muted">api</code>, <code class="rounded px-1 surface-muted">read</code> and <code class="rounded px-1 surface-muted">write</code> policies.</li>
                    <li>4. Allow the hosting server IP through any firewall or address list on the router.</li>
                    <li>5. Restrict the API source address to the hosting server where possible.</li>
                </ul>
                <div class="alert alert-warning mt-4">
                    <span>The hosting server must be able to reach the router over the network. Correct credentials alone are not enough.</span>
                </div>
            </div>

            <x-card title="Recent activity" :pad="false">
                <div class="divide-y border-app border-t">
                    @forelse ($logs->take(8) as $log)
                        <div class="flex items-start gap-3 px-4 py-3">
                            <span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }} flex-none">
                                {{ $log->type }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">{{ Str::limit($log->message, 90) }}</p>
                                <p class="text-xs text-muted">
                                    {{ $log->router?->name }} &middot; {{ $log->created_at->diffForHumans() }}
                                    @if ($log->duration_ms) &middot; {{ $log->duration_ms }} ms @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <x-empty title="No router activity yet" />
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
@endsection
