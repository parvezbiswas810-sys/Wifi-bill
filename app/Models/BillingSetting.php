<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    use BelongsToIsp;

    protected $fillable = [
        'isp_id', 'default_bill_amount', 'default_billing_cycle',
        'invoice_generation_time', 'default_due_days', 'grace_period_days',
        'suspension_time', 'auto_suspend_enabled', 'auto_restore_enabled',
        'notify_on_invoice', 'notify_before_due', 'notify_before_due_days',
        'notify_on_suspension', 'sms_provider', 'sms_config',
    ];

    protected function casts(): array
    {
        return [
            'default_bill_amount' => 'decimal:2',
            'auto_suspend_enabled' => 'boolean',
            'auto_restore_enabled' => 'boolean',
            'notify_on_invoice' => 'boolean',
            'notify_before_due' => 'boolean',
            'notify_on_suspension' => 'boolean',
            'sms_config' => 'array',
        ];
    }
}
