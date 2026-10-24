<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\MikrotikSyncLog;
use App\Models\PppAccount;
use App\Services\Mikrotik\RouterosClient;
use App\Services\Mikrotik\RouterosException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Talks to real MikroTik routers.
 *
 * Every method returns an explicit result array so callers never report a
 * router operation as successful unless RouterOS confirmed it.
 *
 * This service only touches the router and its own logs. Customer-level state
 * such as internet_status is owned by SuspensionService, which is the single
 * place that decides whether a suspension or restoration really happened.
 */
class MikrotikService
{
    protected int $timeout;

    public function __construct()
    {
        $this->timeout = (int) config('services.mikrotik.timeout', env('MIKROTIK_TIMEOUT', 10));
    }

    public function clientFor(MikrotikRouter $router): RouterosClient
    {
        return new RouterosClient(
            host: $router->host,
            port: (int) $router->api_port,
            username: $router->api_username,
            password: $router->api_password,
            useSsl: (bool) $router->use_ssl,
            timeout: $this->timeout,
            sslVerify: (bool) config('services.mikrotik.ssl_verify', false),
        );
    }

    /**
     * Tests connectivity using a real RouterOS command.
     *
     * @return array{ok: bool, message: string, identity?: string, version?: string, uptime?: string, boards?: array}
     */
    public function testConnection(MikrotikRouter $router): array
    {
        $start = microtime(true);

        try {
            $client = $this->clientFor($router);
            $client->connect();

            $resource = $client->queryOne('/system/resource');
            $identity = $client->queryOne('/system/identity');
            $client->disconnect();

            $version = $resource['version'] ?? 'unknown';
            $name = $identity['name'] ?? $router->name;

            $router->forceFill([
                'last_status' => 'online',
                'last_checked_at' => now(),
                'last_error' => null,
            ])->save();

            $this->log($router, 'connection_test', 'success', "Connected to {$name} ({$version})", [
                'version' => $version,
                'board' => $resource['board-name'] ?? null,
                'uptime' => $resource['uptime'] ?? null,
                'architecture' => $resource['architecture-name'] ?? null,
            ], (int) round((microtime(true) - $start) * 1000));

            return [
                'ok' => true,
                'message' => "Connected to {$name}",
                'identity' => $name,
                'version' => $version,
                'uptime' => $resource['uptime'] ?? null,
                'boards' => [
                    'board' => $resource['board-name'] ?? null,
                    'architecture' => $resource['architecture-name'] ?? null,
                    'cpu_load' => $resource['cpu-load'] ?? null,
                    'free_memory' => $resource['free-memory'] ?? null,
                ],
            ];
        } catch (RouterosException $e) {
            return $this->fail($router, $e, $start);
        } catch (\Throwable $e) {
            return $this->fail($router, $e, $start);
        }
    }

    /** @return array{ok: bool, message: string} */
    protected function fail(MikrotikRouter $router, \Throwable $e, float $start): array
    {
        $message = $e->getMessage();

        $router->forceFill([
            'last_status' => 'offline',
            'last_checked_at' => now(),
            'last_error' => mb_substr($message, 0, 500),
        ])->save();

        $this->log($router, 'connection_test', 'failed', $message, null, (int) round((microtime(true) - $start) * 1000));
        Log::warning('MikroTik connection failed', ['router_id' => $router->id, 'error' => $message]);

        return ['ok' => false, 'message' => $message];
    }

    /**
     * Imports PPP secrets and active sessions from the router.
     *
     * Secrets define the billable accounts; sessions tell us who is online now.
     * An inactive secret is still a valid billing account, so it is never
     * treated as a deleted customer.
     *
     * @return array{ok: bool, message: string, secrets: int, sessions: int, changed: int}
     */
    public function syncPpp(MikrotikRouter $router): array
    {
        $start = microtime(true);

        try {
            $client = $this->clientFor($router);
            $client->connect();

            $secrets = $client->query('/ppp/secret');
            $sessions = $client->query('/ppp/active');
            $client->disconnect();
        } catch (\Throwable $e) {
            $this->log($router, 'ppp_sync', 'failed', $e->getMessage(), null, (int) round((microtime(true) - $start) * 1000));

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'secrets' => 0,
                'sessions' => 0,
                'changed' => 0,
            ];
        }

        $sessionByName = [];
        foreach ($sessions as $session) {
            $name = $session['name'] ?? null;
            if ($name !== null) {
                $sessionByName[$name] = $session;
            }
        }

        $seen = [];
        $changed = 0;

        foreach ($secrets as $secret) {
            $username = $secret['name'] ?? null;
            if ($username === null || $username === '') {
                continue;
            }
            $seen[] = $username;

            $session = $sessionByName[$username] ?? null;

            $attributes = [
                'isp_id' => $router->isp_id,
                'router_id' => $router->id,
                'username' => $username,
                'router_secret_id' => $secret['.id'] ?? null,
                'service' => $secret['service'] ?? null,
                'profile' => $secret['profile'] ?? null,
                'local_address' => $secret['local-address'] ?? null,
                'remote_address' => $secret['remote-address'] ?? null,
                'caller_id' => $secret['caller-id'] ?? null,
                'comment' => $secret['comment'] ?? null,
                'disabled' => ($secret['disabled'] ?? 'false') === 'true',
                'is_active_session' => $session !== null,
                'session_uptime' => $session['uptime'] ?? null,
                'session_uptime_seconds' => isset($session['uptime']) ? $this->uptimeToSeconds($session['uptime']) : null,
                'session_caller_id' => $session['caller-id'] ?? null,
                'last_synced_at' => now(),
                'raw' => $secret,
            ];

            $existing = PppAccount::withoutGlobalScope('isp')
                ->where('router_id', $router->id)
                ->where('username', $username)
                ->first();

            if ($existing) {
                $before = $existing->only(['disabled', 'is_active_session', 'profile']);
                $existing->fill($attributes)->save();
                if ($before != $existing->only(['disabled', 'is_active_session', 'profile'])) {
                    $changed++;
                }
            } else {
                PppAccount::create($attributes);
                $changed++;
            }
        }

        // Secrets removed from the router are no longer usable for billing, but
        // customers already bound to them are preserved for the operator to review.
        $removed = PppAccount::withoutGlobalScope('isp')
            ->where('router_id', $router->id)
            ->when($seen !== [], fn ($q) => $q->whereNotIn('username', $seen))
            ->get();

        foreach ($removed as $account) {
            $account->forceFill([
                'is_active_session' => false,
                'session_uptime' => null,
                'session_uptime_seconds' => null,
                'router_secret_id' => null,
                'last_synced_at' => now(),
            ])->save();
        }

        $router->forceFill([
            'last_status' => 'online',
            'last_checked_at' => now(),
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->log($router, 'ppp_sync', 'success', sprintf(
            'Imported %d PPP secrets and %d active sessions', count($secrets), count($sessions)
        ), ['secrets' => count($secrets), 'sessions' => count($sessions), 'changed' => $changed], $duration);

        return [
            'ok' => true,
            'message' => sprintf('Synced %d PPP secrets (%d active sessions)', count($secrets), count($sessions)),
            'secrets' => count($secrets),
            'sessions' => count($sessions),
            'changed' => $changed,
        ];
    }

    /** Converts a RouterOS uptime string (e.g. 1w2d3h4m5s) to seconds. */
    public function uptimeToSeconds(?string $uptime): ?int
    {
        if (! $uptime) {
            return null;
        }

        $units = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        $seconds = 0;
        $matched = false;

        if (preg_match_all('/(\d+)([wdhms])/', $uptime, $m, PREG_SET_ORDER)) {
            foreach ($m as $part) {
                $seconds += ((int) $part[1]) * ($units[$part[2]] ?? 0);
                $matched = true;
            }
        }

        return $matched ? $seconds : null;
    }

    /**
     * Disables a customer's PPP secret on the router.
     *
     * RouterOS confirms by returning the updated secret; the disabled flag is
     * re-read so we never claim success without verification.
     *
     * @return array{ok: bool, message: string, verified: bool}
     */
    public function suspendCustomer(Customer $customer): array
    {
        return $this->setPppDisabled($customer, true);
    }

    /**
     * Re-enables a customer's PPP secret.
     *
     * @return array{ok: bool, message: string, verified: bool}
     */
    public function restoreCustomer(Customer $customer): array
    {
        return $this->setPppDisabled($customer, false);
    }

    /** @return array{ok: bool, message: string, verified: bool} */
    protected function setPppDisabled(Customer $customer, bool $disabled): array
    {
        $router = $customer->router;
        $account = $customer->pppAccount;

        if (! $router) {
            return ['ok' => false, 'message' => 'Customer has no router assigned', 'verified' => false];
        }
        if (! $account) {
            return ['ok' => false, 'message' => 'Customer has no PPP account assigned', 'verified' => false];
        }

        $start = microtime(true);

        try {
            $client = $this->clientFor($router);
            $client->connect();

            // Resolve the secret id from the router when our copy is missing.
            $secretId = $account->router_secret_id;
            if (! $secretId) {
                $found = $client->query('/ppp/secret', ['name' => $account->username]);
                $secretId = $found[0]['.id'] ?? null;
            }

            if (! $secretId) {
                $client->disconnect();

                return $this->recordOperation($customer, $disabled, false, 'PPP secret not found on router', false, $start);
            }

            $client->query('/ppp/secret/set', [
                '.id' => $secretId,
                'disabled' => $disabled ? 'yes' : 'no',
            ]);

            // Read back the stored value - this is the actual confirmation.
            $verify = $client->query('/ppp/secret/print', ['.id' => $secretId]);
            $client->disconnect();

            $isDisabled = ($verify[0]['disabled'] ?? 'false') === 'true';
            $verified = $isDisabled === $disabled;

            $account->forceFill([
                'disabled' => $isDisabled,
                'router_secret_id' => $secretId,
                'last_synced_at' => now(),
            ])->save();

            $message = $verified
                ? ($disabled ? 'PPP secret disabled and verified on router' : 'PPP secret enabled and verified on router')
                : 'Router did not apply the requested change';

            return $this->recordOperation($customer, $disabled, $verified, $message, $verified, $start);
        } catch (\Throwable $e) {
            return $this->recordOperation($customer, $disabled, false, $e->getMessage(), false, $start);
        }
    }

    /**
     * Persists an auditable suspension/restoration attempt.
     *
     * @return array{ok: bool, message: string, verified: bool}
     */
    protected function recordOperation(Customer $customer, bool $disable, bool $routerOk, string $message, bool $verified, float $start): array
    {
        $action = $disable ? 'suspend' : 'restore';
        $status = $routerOk ? 'success' : 'failed';

        // The suspension lifecycle record is written by SuspensionService, which
        // owns that table. Here we only record the router-level operation.
        $this->log($customer->router, $action, $status, $message, [
            'customer_id' => $customer->id,
            'ppp_username' => $customer->ppp_username,
        ], (int) round((microtime(true) - $start) * 1000));

        return ['ok' => $routerOk, 'message' => $message, 'verified' => $verified];
    }

    /**
     * Reads live PPP sessions for a router (used by the connection status view).
     *
     * @return array<string, array<string, string>>
     */
    public function activeSessions(MikrotikRouter $router): array
    {
        try {
            $client = $this->clientFor($router);
            $client->connect();
            $sessions = $client->query('/ppp/active');
            $client->disconnect();

            $byName = [];
            foreach ($sessions as $session) {
                if (isset($session['name'])) {
                    $byName[$session['name']] = $session;
                }
            }

            return $byName;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Whether a PPP username currently has a live session. */
    public function isSessionActive(MikrotikRouter $router, string $username): bool
    {
        $sessions = $this->activeSessions($router);

        return isset($sessions[$username]);
    }

    /** All PPP secrets on the router, keyed by username. */
    public function pppSecrets(MikrotikRouter $router): Collection
    {
        $client = $this->clientFor($router);
        $client->connect();
        $rows = $client->query('/ppp/secret');
        $client->disconnect();

        return collect($rows);
    }

    protected function log(?MikrotikRouter $router, string $type, string $status, ?string $message, ?array $meta = null, ?int $durationMs = null): void
    {
        if (! $router) {
            return;
        }

        MikrotikSyncLog::create([
            'isp_id' => $router->isp_id,
            'router_id' => $router->id,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
            'duration_ms' => $durationMs,
        ]);
    }
}
