<?php

namespace App\View\Composers;

use App\Models\AppNotification;
use App\Models\MikrotikRouter;
use App\Models\MikrotikSyncLog;
use App\Models\PaymentSubmission;
use Illuminate\View\View;

/**
 * Supplies the shared data every authenticated layout needs.
 *
 * All counts are real queries scoped to the signed-in ISP.
 */
class AppLayoutComposer
{
    public function compose(View $view): void
    {
        $ispId = current_isp_id();
        if (! $ispId) {
            return;
        }

        $isp = current_isp();

        $view->with([
            'isp' => $isp,
            'brandInitials' => $this->initials($isp->name ?? config('app.name')),
            'pendingPaymentsCount' => PaymentSubmission::withoutGlobalScope('isp')
                ->where('isp_id', $ispId)
                ->where('status', PaymentSubmission::STATUS_PENDING)
                ->count(),
            'unreadNotificationsCount' => AppNotification::withoutGlobalScope('isp')
                ->where('isp_id', $ispId)
                ->where('audience', 'admin')
                ->whereNull('read_at')
                ->count(),
            'mikrotikAlert' => $this->routerAlert($ispId),
        ]);
    }

    /** A short warning when a router is offline or a recent operation failed. */
    protected function routerAlert(int $ispId): ?string
    {
        $offline = MikrotikRouter::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('is_enabled', true)
            ->where('last_status', 'offline')
            ->get();

        if ($offline->isNotEmpty()) {
            return $offline->count().' router(s) unreachable: '.$offline->pluck('name')->take(3)->implode(', ')
                .'. Displayed PPP data may be stale.';
        }

        $recentFailures = MikrotikSyncLog::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('type', ['suspend', 'restore'])
            ->count();

        if ($recentFailures > 0) {
            return $recentFailures.' suspension/restoration attempt(s) failed in the last 24 hours. Check the sync log.';
        }

        return null;
    }

    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'AX';
    }
}
