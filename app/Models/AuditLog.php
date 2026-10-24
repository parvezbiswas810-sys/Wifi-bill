<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToIsp;

    protected $fillable = [
        'isp_id', 'actor_type', 'actor_id', 'action', 'auditable_type',
        'auditable_id', 'description', 'old_values', 'new_values', 'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function record(string $action, ?Model $subject = null, ?string $description = null, array $old = [], array $new = []): self
    {
        $actor = auth()->user();

        return static::create([
            'isp_id' => current_isp_id(),
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
