<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToIsp, HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = [
        'amount_paid' => 0,
        'balance' => 0,
        'status' => 'unpaid',
        'generated_by' => 'system',
    ];

    protected $fillable = [
        'isp_id', 'customer_id', 'invoice_number', 'billing_period_start',
        'billing_period_end', 'period_key', 'issue_date', 'due_date',
        'suspension_date', 'amount', 'amount_paid', 'balance', 'status',
        'generated_by', 'notes', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'suspension_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function pendingSubmissions(): HasMany
    {
        return $this->submissions()->where('status', 'pending');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIAL], true)
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_PAID) {
            return 'Paid';
        }
        if ($this->status === self::STATUS_CANCELLED) {
            return 'Cancelled';
        }
        if ($this->status === self::STATUS_PARTIAL) {
            return $this->isOverdue() ? 'Overdue' : 'Partially Paid';
        }
        if ($this->pendingSubmissions()->exists()) {
            return 'Pending Verification';
        }
        if ($this->isOverdue()) {
            return 'Overdue';
        }

        return 'Unpaid';
    }

    public function badgeClass(): string
    {
        return match ($this->statusLabel()) {
            'Paid' => 'badge-success',
            'Partially Paid' => 'badge-info',
            'Pending Verification' => 'badge-warning',
            'Overdue' => 'badge-danger',
            'Cancelled' => 'badge-muted',
            default => 'badge-warning',
        };
    }

    /** Recomputes balance and status from recorded payments. */
    public function recalculate(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $this->amount_paid = $paid;
        $this->balance = max(0, round((float) $this->amount - $paid, 2));

        if ($this->status !== self::STATUS_CANCELLED) {
            if ($this->balance <= 0) {
                $this->status = self::STATUS_PAID;
            } elseif ($paid > 0) {
                $this->status = self::STATUS_PARTIAL;
            } else {
                $this->status = self::STATUS_UNPAID;
            }
        }
        $this->save();
    }
}
