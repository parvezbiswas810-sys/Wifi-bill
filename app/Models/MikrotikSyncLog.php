<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikSyncLog extends Model
{
    use BelongsToIsp;

    protected $fillable = [
        'isp_id', 'router_id', 'type', 'status', 'message', 'meta', 'duration_ms',
    ];

    protected $casts = ['meta' => 'array'];

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }
}
