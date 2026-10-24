<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\IspProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * ISP administrator authentication (/loginisp).
 */
class IspLoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('isp')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.isp-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = mb_strtolower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Accept either the username or the email address.
        $user = User::where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'This administrator account is disabled.',
            ]);
        }

        if (! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'username' => 'This account cannot access the administrator area.',
            ]);
        }

        if ($user->isp && ! $user->isp->is_active) {
            throw ValidationException::withMessages([
                'username' => 'This ISP account is inactive.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('isp')->login($user, $request->boolean('remember'));
        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        app()->instance('current_isp_id', (int) $user->isp_id);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('isp')->logout();
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('isp.login')->with('success', 'You have been signed out.');
    }
}
