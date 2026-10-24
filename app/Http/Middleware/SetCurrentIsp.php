<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the ISP tenant for the current request.
 *
 * Runs after authentication so that every downstream query, model event and
 * policy check is automatically scoped to the signed-in ISP.
 */
class SetCurrentIsp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('isp') ?? $request->user('web') ?? $request->user('customer');

        if ($user && $user->isp_id) {
            app()->instance('current_isp_id', (int) $user->isp_id);

            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                if (! $user->is_active) {
                    auth()->guard('isp')->logout();
                    auth()->guard('web')->logout();

                    return redirect()->route('isp.login')
                        ->withErrors(['username' => 'This administrator account is disabled.']);
                }
                if ($user->isp && ! $user->isp->is_active) {
                    auth()->guard('isp')->logout();

                    return redirect()->route('isp.login')
                        ->withErrors(['username' => 'This ISP account is inactive.']);
                }
            }
        }

        return $next($request);
    }
}
