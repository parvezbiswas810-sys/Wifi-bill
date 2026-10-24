<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PppAccount extends Model
{
    use BelongsToIsp, HasFactory;

    protected $attributes = [
        'disabled' => false,
        'is_active_session' => false,
    ];

    protected $fillable = [
        'isp_id', 'router_id', 'username', 'router_secret_id', 'service', 'profile', 'local_address',
        'remote_address', 'caller_id', 'comment', 'disabled', 'is_active_session',
        'session_uptime', 'session_uptime_seconds', 'session_caller_id',
        'last_synced_at', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'is_active_session' => 'boolean',
            'last_synced_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'ppp_account_id');
    }

    public function isAssigned(): bool
    {
        return $this->customer()->exists();
    }
}
