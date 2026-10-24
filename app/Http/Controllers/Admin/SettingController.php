<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\IspProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index()
    {
        $isp = current_isp();
        $billing = BillingSetting::firstOrCreate(['isp_id' => $isp->id], []);
        $auditLogs = AuditLog::orderByDesc('id')->limit(25)->get();

        return view('admin.settings.index', compact('isp', 'billing', 'auditLogs'));
    }

    /** ISP profile, billing policy and notification settings. */
    public function updateProfile(Request $request)
    {
        $isp = current_isp();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:190'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'currency' => ['required', 'string', 'max:8'],
            'invoice_prefix' => ['required', 'string', 'max:16', 'alpha_dash'],
        ]);

        $before = $isp->only(array_keys($data));
        $isp->update($data);

        AuditLog::record('isp_profile.updated', $isp, 'Updated ISP profile', $before, $data);

        return back()->with('success', 'ISP profile saved.');
    }

    public function updateBilling(Request $request)
    {
        $billing = BillingSetting::firstOrCreate(['isp_id' => current_isp_id()], []);

        $data = $request->validate([
            'default_bill_amount' => ['required', 'numeric', 'min:0'],
            'default_billing_cycle' => ['required', Rule::in(['monthly', 'weekly', 'quarterly', 'yearly'])],
            'invoice_generation_time' => ['required', 'date_format:H:i'],
            'default_due_days' => ['required', 'integer', 'min:0', 'max:90'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:90'],
            'suspension_time' => ['required', 'date_format:H:i'],
            'notify_before_due_days' => ['required', 'integer', 'min:0', 'max:30'],
            'sms_provider' => ['required', Rule::in(['none', 'bulksmsbd', 'greenweb', 'custom'])],
        ]);

        $data['auto_suspend_enabled'] = $request->boolean('auto_suspend_enabled');
        $data['auto_restore_enabled'] = $request->boolean('auto_restore_enabled');
        $data['notify_on_invoice'] = $request->boolean('notify_on_invoice');
        $data['notify_before_due'] = $request->boolean('notify_before_due');
        $data['notify_on_suspension'] = $request->boolean('notify_on_suspension');

        $billing->update($data);

        AuditLog::record('billing_settings.updated', $billing, 'Updated billing settings', new: $data);

        return back()->with('success', 'Billing settings saved.');
    }

    /** Rotates the signed-in administrator's password. */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user('isp');

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        AuditLog::record('user.password_changed', $user, 'Changed administrator password');

        return back()->with('success', 'Password updated.');
    }

    /** Ends every other session for this administrator. */
    public function logoutOtherSessions(Request $request)
    {
        $user = $request->user('isp');

        DB::table('sessions')->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        AuditLog::record('user.sessions_revoked', $user, 'Revoked other active sessions');

        return back()->with('success', 'Other sessions have been signed out.');
    }
}
