<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MikrotikRouter extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    protected $attributes = [
        'api_port' => 8728,
        'use_ssl' => false,
        'connection_mode' => 'api',
        'is_enabled' => true,
        'last_status' => 'unknown',
    ];

    protected $fillable = [
        'isp_id', 'name', 'host', 'api_port', 'api_username', 'api_password',
        'use_ssl', 'connection_mode', 'notes', 'is_enabled', 'last_status',
        'last_checked_at', 'last_synced_at', 'last_error',
    ];

    protected $hidden = ['api_password'];

    protected function casts(): array
    {
        return [
            'api_password' => 'encrypted',
            'use_ssl' => 'boolean',
            'is_enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function pppAccounts(): HasMany
    {
        return $this->hasMany(PppAccount::class, 'router_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'router_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(MikrotikSyncLog::class, 'router_id');
    }

    public function isOnline(): bool
    {
        return $this->last_status === 'online';
    }

    public function endpoint(): string
    {
        return ($this->use_ssl ? 'ssl://' : '').$this->host.':'.$this->api_port;
    }
}
