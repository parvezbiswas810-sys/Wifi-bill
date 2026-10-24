<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use BelongsToIsp, HasFactory;

    protected $fillable = [
        'isp_id', 'name', 'type', 'account_number', 'account_name', 'instructions',
        'logo_path', 'requires_reference', 'requires_sender', 'is_enabled', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_reference' => 'boolean',
            'requires_sender' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('name');
    }
}
