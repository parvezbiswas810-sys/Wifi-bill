<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'isp_id', 'name', 'username', 'email', 'password', 'role', 'is_active',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Mirrors the database defaults so a freshly created model behaves the
     * same as the persisted row, before any refresh.
     */
    protected $attributes = [
        'role' => 'isp_admin',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(IspProfile::class, 'isp_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'isp_admin' || $this->role === 'super_admin';
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = $value && ! str_starts_with((string) $value, '$2y$')
            ? Hash::make($value)
            : $value;
    }
}
