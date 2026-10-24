@extends('layouts.app')

@section('title', 'Settings')
@section('subtitle', 'ISP profile, billing policy, security and audit trail')

@section('content')
    <div x-data="{ tab: 'profile' }" class="space-y-4">
        <div class="flex flex-wrap gap-1 rounded-xl p-1 surface-muted">
            @foreach (['profile' => 'ISP Profile', 'billing' => 'Billing', 'security' => 'Security', 'audit' => 'Audit Log'] as $key => $label)
                <button type="button" class="btn btn-sm" :class="tab === '{{ $key }}' ? 'btn-primary' : 'btn-ghost'"
                        @click="tab = '{{ $key }}'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ============ ISP profile ============ --}}
        <div x-show="tab === 'profile'" x-cloak>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-card title="ISP profile" subtitle="Shown on invoices and the customer portal">
                        <form method="POST" action="{{ route('admin.settings.profile') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <div class="sm:col-span-2">
                                <label for="name" class="label label-req">ISP name</label>
                                <input id="name" name="name" type="text" required value="{{ old('name', $isp->name) }}" class="input">
                                @error('name')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="contact_number" class="label">Contact number</label>
                                <input id="contact_number" name="contact_number" type="text" value="{{ old('contact_number', $isp->contact_number) }}" class="input">
                            </div>
                            <div>
                                <label for="email" class="label">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $isp->email) }}" class="input">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address" class="label">Address</label>
                                <textarea id="address" name="address" rows="2" class="textarea">{{ old('address', $isp->address) }}</textarea>
                            </div>
                            <div>
                                <label for="website" class="label">Website</label>
                                <input id="website" name="website" type="url" value="{{ old('website', $isp->website) }}" class="input" placeholder="https://example.com">
                            </div>
                            <div>
                                <label for="invoice_prefix" class="label label-req">Invoice prefix</label>
                                <input id="invoice_prefix" name="invoice_prefix" type="text" required value="{{ old('invoice_prefix', $isp->invoice_prefix) }}" class="input">
                                <p class="hint">Invoice numbers look like {{ $isp->invoice_prefix }}-{{ now()->format('Y') }}-00001</p>
                            </div>
                            <div>
                                <label for="timezone" class="label label-req">Timezone</label>
                                <select id="timezone" name="timezone" class="select">
                                    @foreach (['Asia/Dhaka', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Dubai', 'UTC'] as $tz)
                                        <option value="{{ $tz }}" @selected(old('timezone', $isp->timezone) === $tz)>{{ $tz }}</option>
                                    @endforeach
                                </select>
                                <p class="hint">All invoice and suspension times use this timezone.</p>
                            </div>
                            <div>
                                <label for="currency" class="label label-req">Currency</label>
                                <input id="currency" name="currency" type="text" required value="{{ old('currency', $isp->currency) }}" class="input" maxlength="8">
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="btn btn-primary">Save ISP profile</button>
                            </div>
                        </form>
                    </x-card>
                </div>

                <x-card title="Current configuration">
                    <dl>
                        <div class="kv"><dt>ISP name</dt><dd>{{ $isp->name }}</dd></div>
                        <div class="kv"><dt>Timezone</dt><dd>{{ $isp->timezone }}</dd></div>
                        <div class="kv"><dt>Currency</dt><dd>{{ $isp->currency }}</dd></div>
                        <div class="kv"><dt>Routers</dt><dd>{{ $isp->routers()->count() }}</dd></div>
                        <div class="kv"><dt>Customers</dt><dd>{{ $isp->customers()->count() }}</dd></div>
                        <div class="kv"><dt>Payment methods</dt><dd>{{ $isp->paymentMethods()->count() }}</dd></div>
                    </dl>
                </x-card>
            </div>
        </div>

        {{-- ============ Billing ============ --}}
        <div x-show="tab === 'billing'" x-cloak>
            <x-card title="Billing policy" subtitle="Defaults applied to new customers and the scheduler">
                <form method="POST" action="{{ route('admin.settings.billing') }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="default_bill_amount" class="label label-req">Default bill amount</label>
                            <input id="default_bill_amount" name="default_bill_amount" type="number" step="0.01" min="0" required
                                   value="{{ old('default_bill_amount', $billing->default_bill_amount) }}" class="input">
                        </div>
                        <div>
                            <label for="default_billing_cycle" class="label label-req">Billing cycle</label>
                            <select id="default_billing_cycle" name="default_billing_cycle" class="select">
                                @foreach (['monthly' => 'Monthly', 'weekly' => 'Weekly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('default_billing_cycle', $billing->default_billing_cycle) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="invoice_generation_time" class="label label-req">Invoice generation time</label>
                            <input id="invoice_generation_time" name="invoice_generation_time" type="time" required
                                   value="{{ old('invoice_generation_time', substr($billing->invoice_generation_time, 0, 5)) }}" class="input">
                        </div>
                        <div>
                            <label for="default_due_days" class="label label-req">Due after (days)</label>
                            <input id="default_due_days" name="default_due_days" type="number" min="0" max="90" required
                                   value="{{ old('default_due_days', $billing->default_due_days) }}" class="input">
                        </div>
                        <div>
                            <label for="grace_period_days" class="label label-req">Grace period (days)</label>
                            <input id="grace_period_days" name="grace_period_days" type="number" min="0" max="90" required
                                   value="{{ old('grace_period_days', $billing->grace_period_days) }}" class="input">
                        </div>
                        <div>
                            <label for="suspension_time" class="label label-req">Suspension time</label>
                            <input id="suspension_time" name="suspension_time" type="time" required
                                   value="{{ old('suspension_time', substr($billing->suspension_time, 0, 5)) }}" class="input">
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="auto_suspend_enabled" value="1" class="checkbox" @checked(old('auto_suspend_enabled', $billing->auto_suspend_enabled))>
                            <span>Enable automatic suspension</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="auto_restore_enabled" value="1" class="checkbox" @checked(old('auto_restore_enabled', $billing->auto_restore_enabled))>
                            <span>Enable automatic restoration after payment</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="notify_on_invoice" value="1" class="checkbox" @checked(old('notify_on_invoice', $billing->notify_on_invoice))>
                            <span>Notify on invoice generation</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="notify_before_due" value="1" class="checkbox" @checked(old('notify_before_due', $billing->notify_before_due))>
                            <span>Notify before the due date</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="notify_on_suspension" value="1" class="checkbox" @checked(old('notify_on_suspension', $billing->notify_on_suspension))>
                            <span>Notify on suspension</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="notify_before_due_days" class="label label-req">Notify this many days before due</label>
                            <input id="notify_before_due_days" name="notify_before_due_days" type="number" min="0" max="30" required
                                   value="{{ old('notify_before_due_days', $billing->notify_before_due_days) }}" class="input">
                        </div>
                        <div>
                            <label for="sms_provider" class="label label-req">SMS provider</label>
                            <select id="sms_provider" name="sms_provider" class="select">
                                @foreach (['none' => 'None (in-app only)', 'bulksmsbd' => 'BulkSMSBD', 'greenweb' => 'GreenWeb', 'custom' => 'Custom provider'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('sms_provider', $billing->sms_provider) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="hint">In-app notifications always work. External SMS requires provider credentials and confirmation before it is reported as sent.</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save billing settings</button>
                </form>
            </x-card>

            <div class="card card-pad mt-4">
                <p class="font-semibold">Scheduled jobs</p>
                <p class="mt-1 text-sm text-muted">
                    The scheduler must run once per minute from cron for invoicing and suspension to work:
                </p>
                <pre class="mt-3 overflow-x-auto rounded-xl p-3 text-xs surface-muted"><code>* * * * * cd {{ base_path() }} && {{ PHP_BINARY }} artisan schedule:run >> /dev/null 2>&1</code></pre>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="badge badge-info">billing:generate-invoices every minute</span>
                    <span class="badge badge-info">billing:suspend-due every minute</span>
                    <span class="badge badge-info">billing:mark-overdue daily 07:00</span>
                    <span class="badge badge-info">mikrotik:sync every five minutes</span>
                </div>
            </div>
        </div>

        {{-- ============ Security ============ --}}
        <div x-show="tab === 'security'" x-cloak>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <x-card title="Change password">
                    <form method="POST" action="{{ route('admin.settings.password') }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="current_password" class="label label-req">Current password</label>
                            <input id="current_password" name="current_password" type="password" required class="input" autocomplete="current-password">
                            @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password" class="label label-req">New password</label>
                            <input id="password" name="password" type="password" required class="input" autocomplete="new-password">
                            <p class="hint">At least 8 characters.</p>
                            @error('password')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="label label-req">Confirm new password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required class="input" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary">Update password</button>
                    </form>
                </x-card>

                <div class="space-y-4">
                    <x-card title="Session management">
                        <p class="text-sm text-muted">
                            Signing out other sessions immediately ends access from any other browser or device.
                        </p>
                        <form method="POST" action="{{ route('admin.settings.revoke-sessions') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Sign out other sessions</button>
                        </form>
                    </x-card>

                    <x-card title="Login security">
                        <dl>
                            <div class="kv"><dt>Last login</dt><dd>{{ auth('isp')->user()->last_login_at?->format('d M Y H:i') ?? 'never' }}</dd></div>
                            <div class="kv"><dt>Last login IP</dt><dd>{{ auth('isp')->user()->last_login_ip ?? '-' }}</dd></div>
                            <div class="kv"><dt>Login throttling</dt><dd>5 attempts / 5 minutes</dd></div>
                            <div class="kv"><dt>Router credentials</dt><dd>Encrypted at rest</dd></div>
                            <div class="kv"><dt>Customer passwords</dt><dd>Bcrypt hashed</dd></div>
                        </dl>
                    </x-card>
                </div>
            </div>
        </div>

        {{-- ============ Audit ============ --}}
        <div x-show="tab === 'audit'" x-cloak>
            <x-card title="Audit log" subtitle="Recent financial and configuration changes" :pad="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>When</th><th>Action</th><th>Description</th><th>Actor</th><th>IP</th></tr></thead>
                        <tbody>
                            @forelse ($auditLogs as $log)
                                <tr>
                                    <td class="whitespace-nowrap text-sm">{{ $log->created_at->format('d M Y H:i') }}</td>
                                    <td><span class="badge badge-muted">{{ $log->action }}</span></td>
                                    <td class="text-sm">{{ $log->description }}</td>
                                    <td class="text-sm">{{ ucfirst($log->actor_type) }} #{{ $log->actor_id ?? '-' }}</td>
                                    <td class="text-xs text-muted">{{ $log->ip_address ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty title="No audit entries yet" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-pad border-t border-app">
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-secondary btn-sm">Open full audit log</a>
                </div>
            </x-card>
        </div>
    </div>
@endsection
