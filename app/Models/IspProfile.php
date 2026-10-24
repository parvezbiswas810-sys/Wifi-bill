<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IspProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'logo_path', 'contact_number', 'email', 'address',
        'website', 'timezone', 'currency', 'invoice_prefix', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'isp_id');
    }

    public function routers(): HasMany
    {
        return $this->hasMany(MikrotikRouter::class, 'isp_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'isp_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'isp_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'isp_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'isp_id');
    }

    public function billingSetting(): HasOne
    {
        return $this->hasOne(BillingSetting::class, 'isp_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(IspSetting::class, 'isp_id');
    }

    /** Reads a namespaced setting value with a default. */
    public function setting(string $key, $default = null)
    {
        $row = $this->settings()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public function putSetting(string $key, $value): void
    {
        $this->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
