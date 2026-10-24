<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternetSuspensionLog extends Model
{
    use BelongsToIsp;

    public const ACTION_SUSPEND = 'suspend';

    public const ACTION_RESTORE = 'restore';

    protected $fillable = [
        'isp_id', 'customer_id', 'invoice_id', 'router_id', 'action', 'status',
        'reason', 'message', 'router_response', 'performed_by', 'user_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }
}
