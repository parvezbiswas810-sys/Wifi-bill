<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use BelongsToIsp;

    protected $table = 'app_notifications';

    protected $fillable = [
        'isp_id', 'audience', 'customer_id', 'user_id', 'type', 'title', 'body',
        'data', 'severity', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForAdmin($query)
    {
        return $query->where('audience', 'admin');
    }

    /** Delivers an in-app notification to the ISP administrators. */
    public static function notifyAdmin(int $ispId, string $type, string $title, ?string $body = null, array $data = [], string $severity = 'info'): self
    {
        return static::create([
            'isp_id' => $ispId,
            'audience' => 'admin',
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'severity' => $severity,
        ]);
    }

    public static function notifyCustomer(int $ispId, int $customerId, string $type, string $title, ?string $body = null, array $data = [], string $severity = 'info'): self
    {
        return static::create([
            'isp_id' => $ispId,
            'audience' => 'customer',
            'customer_id' => $customerId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'severity' => $severity,
        ]);
    }
}
