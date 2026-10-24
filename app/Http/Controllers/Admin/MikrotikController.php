<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\MikrotikSyncLog;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MikrotikController extends Controller
{
    public function index()
    {
        $routers = MikrotikRouter::withCount(['pppAccounts', 'customers'])
            ->orderBy('name')->get();

        $logs = MikrotikSyncLog::with('router:id,name')
            ->orderByDesc('id')->limit(40)->get();

        return view('admin.mikrotik.index', compact('routers', 'logs'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $router = MikrotikRouter::create($data + ['isp_id' => current_isp_id()]);

        \App\Models\AuditLog::record('router.created', $router, "Added router {$router->name} ({$router->host})");

        return back()->with('success', "Router \"{$router->name}\" saved. Use Test Connection to verify it is reachable.");
    }

    public function update(Request $request, MikrotikRouter $router)
    {
        $data = $this->validated($request, $router);

        // Keep the stored password when the field is left blank.
        if (empty($data['api_password'])) {
            unset($data['api_password']);
        }

        $router->update($data);

        \App\Models\AuditLog::record('router.updated', $router, "Updated router {$router->name}");

        return back()->with('success', 'Router updated.');
    }

    public function destroy(MikrotikRouter $router)
    {
        if ($router->customers()->exists()) {
            return back()->with('error', 'This router still has customers assigned. Reassign them first.');
        }

        \App\Models\AuditLog::record('router.deleted', $router, "Deleted router {$router->name}");
        $router->delete();

        return back()->with('success', 'Router removed.');
    }

    /** Runs a real RouterOS connectivity test. */
    public function test(MikrotikRouter $router, MikrotikService $service)
    {
        $result = $service->testConnection($router);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** Imports PPP secrets and active sessions. */
    public function sync(MikrotikRouter $router, MikrotikService $service)
    {
        $result = $service->syncPpp($router);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** Disconnects the client API connection (no router-side change). */
    public function disconnect(MikrotikRouter $router)
    {
        return response()->json([
            'ok' => true,
            'message' => 'API connection closed. The router configuration was not modified.',
        ]);
    }

    public function logs(Request $request)
    {
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $logs = MikrotikSyncLog::with('router:id,name')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(40)->withQueryString();

        return view('admin.mikrotik.logs', compact('logs', 'type', 'status'));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?MikrotikRouter $router = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:190'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_username' => ['required', 'string', 'max:100'],
            'api_password' => [$router ? 'nullable' : 'required', 'string', 'max:190'],
            'use_ssl' => ['nullable', 'boolean'],
            'connection_mode' => ['required', Rule::in(['api', 'api-ssl'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]) + [
            'use_ssl' => $request->boolean('use_ssl'),
            'is_enabled' => $request->boolean('is_enabled', true),
        ];
    }
}
