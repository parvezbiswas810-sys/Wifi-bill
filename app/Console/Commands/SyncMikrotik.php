<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Illuminate\Console\Command;

class SyncMikrotik extends Command
{
    protected $signature = 'mikrotik:sync {--router= : Sync only this router id}';

    protected $description = 'Synchronize PPP accounts and active sessions from MikroTik routers';

    public function handle(MikrotikService $service): int
    {
        $routers = MikrotikRouter::withoutGlobalScope('isp')
            ->where('is_enabled', true)
            ->when($this->option('router'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($routers->isEmpty()) {
            $this->warn('No enabled routers configured.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($routers as $router) {
            $result = bind_isp($router->isp_id, fn () => $service->syncPpp($router));

            if ($result['ok']) {
                $ok++;
                $this->info("[{$router->name}] {$result['message']}");
            } else {
                $failed++;
                $this->error("[{$router->name}] {$result['message']}");
            }
        }

        $this->info("Sync complete: {$ok} succeeded, {$failed} failed");

        return self::SUCCESS;
    }
}
