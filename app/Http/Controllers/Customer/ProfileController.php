<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show()
    {
        $customer = auth('customer')->user();
        $customer->load(['router:id,name,last_status,last_synced_at', 'pppAccount']);

        return view('customer.profile', compact('customer'));
    }

    /** Connection details - never exposes router addresses or credentials. */
    public function connection()
    {
        $customer = auth('customer')->user();
        $customer->load(['router:id,name,last_status,last_synced_at', 'pppAccount']);

        $suspensions = $customer->suspensionLogs()->orderByDesc('id')->limit(10)->get();

        return view('customer.connection', compact('customer', 'suspensions'));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ]);

        $customer = $request->user('customer');

        if (! Hash::check($data['current_password'], $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $customer->forceFill(['password' => $data['password']])->save();

        return back()->with('success', 'Your password has been updated.');
    }

    public function notifications()
    {
        $customer = auth('customer')->user();

        $notifications = $customer->notifications()->orderByDesc('id')->paginate(20);

        AppNotification::where('customer_id', $customer->id)->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('customer.notifications', compact('notifications'));
    }
}
