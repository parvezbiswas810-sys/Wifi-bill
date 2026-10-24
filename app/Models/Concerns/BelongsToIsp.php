<?php

namespace App\Models\Concerns;

use App\Models\IspProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the ISP currently bound to the request, so one ISP
 * administrator can never read or write another ISP's rows.
 */
trait BelongsToIsp
{
    public static function bootBelongsToIsp(): void
    {
        static::addGlobalScope('isp', function (Builder $query) {
            $ispId = current_isp_id();
            if ($ispId !== null) {
                $query->where($query->getModel()->getTable().'.isp_id', $ispId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->isp_id)) {
                $ispId = current_isp_id();
                if ($ispId !== null) {
                    $model->isp_id = $ispId;
                }
            }
        });
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(IspProfile::class, 'isp_id');
    }

    /** Query a specific ISP regardless of the bound tenant. */
    public function scopeForIsp(Builder $query, int $ispId): Builder
    {
        return $query->withoutGlobalScope('isp')->where($this->getTable().'.isp_id', $ispId);
    }
}
