<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Customer billing portal authentication (/).
 */
class CustomerLoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('auth.customer-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'customer|'.mb_strtolower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Customers log in with their portal username only; the lookup is not
        // tenant-scoped because the tenant is not yet known at this point.
        $customer = Customer::withoutGlobalScope('isp')
            ->where('username', $credentials['username'])
            ->first();

        if (! $customer || ! Hash::check($credentials['password'], $customer->password)) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if ($customer->status === 'archived') {
            throw ValidationException::withMessages([
                'username' => 'This account is no longer active. Please contact your ISP.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();

        $customer->forceFill(['last_login_at' => now()])->save();
        app()->instance('current_isp_id', (int) $customer->isp_id);

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login')->with('success', 'You have been signed out.');
    }
}
