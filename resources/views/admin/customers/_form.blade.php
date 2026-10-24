@php($isEdit = isset($customer))

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- ============ Left: identity + billing ============ --}}
    <div class="space-y-4 lg:col-span-2">
        <x-card title="Customer details" subtitle="Contact and portal login information">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="label label-req">Customer name</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $customer->name ?? '') }}"
                           class="input @error('name') input-error @enderror">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="label">Phone number</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $customer->phone ?? '') }}"
                           class="input @error('phone') input-error @enderror" placeholder="01XXXXXXXXX">
                    @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="address" class="label">Address (optional)</label>
                    <textarea id="address" name="address" rows="2"
                              class="textarea @error('address') input-error @enderror">{{ old('address', $customer->address ?? '') }}</textarea>
                    @error('address')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="username" class="label label-req">Portal username</label>
                    <input id="username" name="username" type="text" required
                           value="{{ old('username', $customer->username ?? '') }}"
                           class="input @error('username') input-error @enderror" placeholder="e.g. rahim01">
                    <p class="hint">Used to sign in at the billing portal.</p>
                    @error('username')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="label {{ $isEdit ? '' : 'label-req' }}">Portal password</label>
                    <input id="password" name="password" type="password" @if (! $isEdit) required @endif
                           class="input @error('password') input-error @enderror"
                           placeholder="{{ $isEdit ? 'Leave blank to keep the current password' : 'Minimum 6 characters' }}"
                           autocomplete="new-password">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-card>

        <x-card title="Billing configuration" subtitle="Each customer is billed on their own schedule">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="monthly_bill_amount" class="label label-req">Monthly bill amount</label>
                    <input id="monthly_bill_amount" name="monthly_bill_amount" type="number" step="0.01" min="0" required
                           value="{{ old('monthly_bill_amount', $customer->monthly_bill_amount ?? $settings->default_bill_amount) }}"
                           class="input @error('monthly_bill_amount') input-error @enderror">
                    @error('monthly_bill_amount')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="billing_start_date" class="label label-req">Billing start date</label>
                    <input id="billing_start_date" name="billing_start_date" type="date" required
                           value="{{ old('billing_start_date', isset($customer) ? $customer->billing_start_date?->format('Y-m-d') : now()->toDateString()) }}"
                           class="input @error('billing_start_date') input-error @enderror">
                    @error('billing_start_date')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="bill_generation_day" class="label label-req">Bill generation day</label>
                    <select id="bill_generation_day" name="bill_generation_day" required
                            class="select @error('bill_generation_day') input-error @enderror">
                        @for ($d = 1; $d <= 28; $d++)
                            <option value="{{ $d }}" @selected((int) old('bill_generation_day', $customer->bill_generation_day ?? 1) === $d)>
                                Day {{ $d }}
                            </option>
                        @endfor
                    </select>
                    <p class="hint">Limited to 1-28 so every month is valid.</p>
                    @error('bill_generation_day')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="bill_generation_time" class="label">Invoice generation time</label>
                    <input id="bill_generation_time" name="bill_generation_time" type="time"
                           value="{{ old('bill_generation_time', substr($customer->bill_generation_time ?? $settings->invoice_generation_time ?? '00:05', 0, 5)) }}"
                           class="input">
                </div>

                <div>
                    <label for="due_days" class="label label-req">Due after (days)</label>
                    <input id="due_days" name="due_days" type="number" min="0" max="90" required
                           value="{{ old('due_days', $customer->due_days ?? $settings->default_due_days) }}"
                           class="input @error('due_days') input-error @enderror">
                    @error('due_days')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="grace_period_days" class="label label-req">Grace period (days)</label>
                    <input id="grace_period_days" name="grace_period_days" type="number" min="0" max="90" required
                           value="{{ old('grace_period_days', $customer->grace_period_days ?? $settings->grace_period_days) }}"
                           class="input @error('grace_period_days') input-error @enderror">
                    <p class="hint">Suspension happens this many days after the due date.</p>
                    @error('grace_period_days')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="label">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes', $customer->notes ?? '') }}</textarea>
                </div>

                <div class="sm:col-span-2 space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="billing_enabled" value="1" class="checkbox"
                               @checked(old('billing_enabled', $customer->billing_enabled ?? true))>
                        <span>Billing enabled <span class="text-muted">- generate invoices for this customer</span></span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="auto_suspend" value="1" class="checkbox"
                               @checked(old('auto_suspend', $customer->auto_suspend ?? true))>
                        <span>Automatic suspension <span class="text-muted">- disable PPP account when overdue</span></span>
                    </label>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ============ Right: router + PPP selection ============ --}}
    <div class="space-y-4">
        <x-card title="MikroTik assignment" subtitle="Link this customer to a real PPP account">
            <div class="space-y-4" x-data="pppPicker()">
                <div>
                    <label for="router_id" class="label label-req">Router</label>
                    <select id="router_id" name="router_id" required class="select @error('router_id') input-error @enderror"
                            x-model="routerId" @change="loadPpp()">
                        <option value="">Select a router</option>
                        @foreach ($routers as $router)
                            <option value="{{ $router->id }}">{{ $router->name }} ({{ $router->host }})</option>
                        @endforeach
                    </select>
                    @error('router_id')<p class="field-error">{{ $message }}</p>@enderror

                    @if ($routers->isEmpty())
                        <div class="alert alert-warning mt-3">
                            <span>No enabled router yet.
                                <a href="{{ route('admin.mikrotik.index') }}" class="font-semibold underline">Add a MikroTik router</a>
                                before creating customers.</span>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="label label-req">PPP account</label>
                    <div x-show="loading" class="space-y-2">
                        <div class="skeleton h-10 w-full"></div>
                        <p class="hint">Loading PPP accounts from the router data...</p>
                    </div>

                    <div x-show="! loading" x-cloak>
                        <input type="hidden" name="ppp_account_id" :value="selected">
                        <div class="relative" @click.outside="open = false">
                            <button type="button" class="input flex items-center justify-between gap-2 text-left"
                                    @click="open = ! open" :disabled="! routerId">
                                <span class="min-w-0 truncate">
                                    <span x-show="selectedLabel" x-text="selectedLabel"></span>
                                    <span x-show="! selectedLabel" class="text-muted">
                                        <span x-show="! routerId">Select a router first</span>
                                        <span x-show="routerId">Search PPP username</span>
                                    </span>
                                </span>
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none text-muted"><path d="M5.3 7.3 10 12l4.7-4.7-1.4-1.4L10 9.2 6.7 5.9 5.3 7.3Z"/></svg>
                            </button>

                            <div class="menu-panel w-full" x-show="open" x-cloak style="min-width: 100%">
                                <div class="p-1">
                                    <input type="text" class="input" x-model="q" placeholder="Search PPP username" autocomplete="off">
                                </div>
                                <div class="max-h-64 overflow-y-auto scrollbar-thin">
                                    <template x-for="o in filtered" :key="o.value">
                                        <button type="button" class="menu-item"
                                                :class="o.disabled ? 'opacity-50 cursor-not-allowed' : ''"
                                                @click="pick(o)" :disabled="o.disabled">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate" x-text="o.label"></span>
                                                <span class="block truncate text-xs text-muted" x-show="o.meta" x-text="o.meta"></span>
                                            </span>
                                        </button>
                                    </template>
                                    <p class="px-3 py-3 text-sm text-muted" x-show="filtered.length === 0"
                                       x-text="options.length === 0 ? 'No PPP account found on this router. Run a synchronization first.' : 'No match for your search.'"></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 flex items-center gap-2 text-xs text-muted">
                            <span x-show="routerStatus === 'offline'" class="badge badge-danger">Router offline</span>
                            <span x-show="lastSynced" x-text="'Last synced: ' + lastSynced"></span>
                        </div>
                        <p class="hint">
                            Only accounts that exist on the router can be selected. Already assigned accounts are disabled.
                        </p>
                    </div>
                    @error('ppp_account_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="alert alert-info" x-show="selectedLabel" x-cloak>
                    <span>Selected PPP account: <span class="font-semibold" x-text="selectedLabel"></span></span>
                </div>
            </div>
        </x-card>

        @if ($isEdit)
            <x-card title="Current status">
                <dl>
                    <div class="kv"><dt>Customer code</dt><dd>{{ $customer->customer_code }}</dd></div>
                    <div class="kv"><dt>Internet status</dt><dd>{{ $customer->internetStatusLabel() }}</dd></div>
                    <div class="kv"><dt>Next bill date</dt><dd>{{ $customer->next_bill_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div class="kv"><dt>Next suspension</dt><dd>{{ $customer->next_suspension_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div class="kv"><dt>Outstanding</dt><dd>{{ isp_money($customer->outstandingBalance()) }}</dd></div>
                </dl>
            </x-card>
        @endif
    </div>
</div>

@push('scripts')
<script>
function pppPicker() {
    return {
        routerId: @js((string) old('router_id', $customer->router_id ?? '')),
        selected: @js((string) old('ppp_account_id', $customer->ppp_account_id ?? '')),
        selectedLabel: @js(isset($customer) && $customer->ppp_username ? $customer->ppp_username : ''),
        options: [],
        open: false,
        q: '',
        loading: false,
        routerStatus: '',
        lastSynced: '',

        get filtered() {
            const n = this.q.trim().toLowerCase();
            if (! n) return this.options;
            return this.options.filter(o => o.label.toLowerCase().includes(n) || o.meta.toLowerCase().includes(n));
        },

        init() {
            if (this.routerId) this.loadPpp();
        },

        async loadPpp() {
            this.options = [];
            this.q = '';
            if (! this.routerId) return;

            this.loading = true;
            try {
                const data = await window.apiFetch(`/admin/routers/${this.routerId}/ppp-options`);
                this.options = data.options || [];
                this.routerStatus = data.router_status || '';
                this.lastSynced = data.last_synced_at || '';

                if (this.options.length === 0) {
                    window.toast('No PPP accounts found. Run a router synchronization first.', 'warning');
                }

                // Keep the selection only if it is still valid.
                if (this.selected && ! this.options.some(o => o.value === this.selected)) {
                    this.selected = '';
                    this.selectedLabel = '';
                }
            } catch (e) {
                window.toast('Could not load PPP accounts: ' + e.message, 'error');
            } finally {
                this.loading = false;
            }
        },

        pick(o) {
            if (o.disabled) return;
            this.selected = o.value;
            this.selectedLabel = o.label;
            this.open = false;
            this.q = '';
        },
    };
}
</script>
@endpush
