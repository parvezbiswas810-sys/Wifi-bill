<?php

use App\Models\IspProfile;

if (! function_exists('current_isp_id')) {
    /**
     * The ISP id bound to the active request.
     *
     * Returns the authenticated ISP administrator's tenant, or the tenant of a
     * logged-in customer. Console commands bind it explicitly so that tenant
     * global scopes resolve correctly outside a request.
     */
    function current_isp_id(): ?int
    {
        if (app()->bound('current_isp_id')) {
            $bound = app('current_isp_id');

            return $bound === null ? null : (int) $bound;
        }

        // Resolving an authenticated model triggers that model's isp global
        // scope, which calls straight back into this function. Returning null
        // on re-entry leaves the guard's own lookup unscoped - it is a
        // primary-key fetch, so nothing crosses tenants - and breaks the cycle.
        // SetCurrentIsp binds the real tenant immediately afterwards.
        static $resolving = false;
        if ($resolving) {
            return null;
        }

        $resolving = true;

        try {
            foreach (['isp', 'customer', 'web'] as $guard) {
                $user = auth()->guard($guard)->user();
                if ($user && ! empty($user->isp_id)) {
                    return (int) $user->isp_id;
                }
            }
        } finally {
            $resolving = false;
        }

        return null;
    }
}

if (! function_exists('current_isp')) {
    function current_isp(): ?IspProfile
    {
        $id = current_isp_id();

        return $id ? IspProfile::find($id) : null;
    }
}

if (! function_exists('bind_isp')) {
    /** Runs a callback with the tenant scope pinned to a specific ISP. */
    function bind_isp(?int $ispId, callable $callback)
    {
        $had = app()->bound('current_isp_id');
        $previous = $had ? app('current_isp_id') : null;

        app()->instance('current_isp_id', $ispId);

        try {
            return $callback();
        } finally {
            if ($had) {
                app()->instance('current_isp_id', $previous);
            } else {
                app()->forgetInstance('current_isp_id');
            }
        }
    }
}

if (! function_exists('isp_money')) {
    /** Formats a monetary amount using the ISP currency. */
    function isp_money($amount, ?string $currency = null): string
    {
        $currency ??= current_isp()?->currency ?? 'BDT';

        return $currency.' '.number_format((float) $amount, 2);
    }
}

if (! function_exists('mask_secret')) {
    /** Masks a credential for display without ever revealing it. */
    function mask_secret(?string $value): string
    {
        if (! $value) {
            return '';
        }

        return str_repeat('•', 8);
    }
}
