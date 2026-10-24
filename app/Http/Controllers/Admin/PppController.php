<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\PppAccount;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

/**
 * MikroTik PPP clients, split into active / inactive / all.
 *
 * "Inactive" here means no live session - the account is still a valid billing
 * secret and is never treated as deleted.
 */
class PppController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->string('tab')->toString() ?: 'active';

        $query = PppAccount::query()->with('router:id,name,last_status,last_synced_at');

        if ($routerId = $request->integer('router')) {
            $query->where('router_id', $routerId);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('caller_id', 'like', "%{$search}%")
                    ->orWhere('remote_address', 'like', "%{$search}%")
                    ->orWhere('profile', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        match ($tab) {
            'inactive' => $query->where('is_active_session', false),
            'all' => null,
            default => $query->where('is_active_session', true),
        };

        $accounts = $query->orderBy('username')->paginate(25)->withQueryString();

        // Which accounts are already attached to a customer.
        $assigned = Customer::withTrashed()
            ->whereIn('ppp_account_id', $accounts->pluck('id'))
            ->get(['id', 'ppp_account_id', 'customer_code', 'name'])
            ->keyBy('ppp_account_id');

        $counts = [
            'active' => PppAccount::where('is_active_session', true)->count(),
            'inactive' => PppAccount::where('is_active_session', false)->count(),
            'all' => PppAccount::count(),
            'unassigned' => PppAccount::whereNotIn(
                'id',
                Customer::withTrashed()->whereNotNull('ppp_account_id')->pluck('ppp_account_id')
            )->count(),
        ];

        $routers = MikrotikRouter::orderBy('name')->get();

        return view('admin.ppp.index', compact('accounts', 'tab', 'counts', 'routers', 'assigned'));
    }

    /** Refreshes PPP data for one router (AJAX). */
    public function sync(Request $request, MikrotikRouter $router, MikrotikService $service)
    {
        $result = $service->syncPpp($router);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $result['ok'],
                'message' => $result['message'],
                'secrets' => $result['secrets'],
                'sessions' => $result['sessions'],
            ], $result['ok'] ? 200 : 422);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
