<?php

namespace App\Models;

use App\Models\Concerns\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    protected $fillable = [
        'isp_id', 'customer_code', 'name', 'phone', 'address', 'username', 'password',
        'router_id', 'ppp_account_id', 'ppp_username', 'monthly_bill_amount',
        'billing_enabled', 'billing_start_date', 'bill_generation_day',
        'bill_generation_time', 'next_bill_date', 'last_billed_on', 'due_days',
        'grace_period_days', 'next_suspension_date', 'suspension_time',
        'auto_suspend', 'internet_status', 'suspension_owned_by_billing',
        'last_suspended_at', 'last_restored_at', 'status', 'notes', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Mirrors the database defaults.
     *
     * These matter for business rules: a customer created in memory must report
     * auto_suspend = true, otherwise suspension would silently skip them.
     */
    protected $attributes = [
        'monthly_bill_amount' => 0,
        'billing_enabled' => true,
        'bill_generation_day' => 1,
        'bill_generation_time' => '00:05:00',
        'due_days' => 10,
        'grace_period_days' => 3,
        'suspension_time' => '00:00:00',
        'auto_suspend' => true,
        'internet_status' => 'unknown',
        'suspension_owned_by_billing' => false,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'monthly_bill_amount' => 'decimal:2',
            'billing_enabled' => 'boolean',
            'auto_suspend' => 'boolean',
            'suspension_owned_by_billing' => 'boolean',
            'billing_start_date' => 'date',
            'next_bill_date' => 'date',
            'last_billed_on' => 'date',
            'next_suspension_date' => 'date',
            'last_suspended_at' => 'datetime',
            'last_restored_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }

    public function pppAccount(): BelongsTo
    {
        return $this->belongsTo(PppAccount::class, 'ppp_account_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function suspensionLogs(): HasMany
    {
        return $this->hasMany(InternetSuspensionLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function outstandingInvoices()
    {
        return $this->invoices()->whereIn('status', ['unpaid', 'partial', 'overdue']);
    }

    public function outstandingBalance(): float
    {
        return (float) $this->outstandingInvoices()->sum('balance');
    }

    public function latestInvoice(): ?Invoice
    {
        return $this->invoices()->orderByDesc('issue_date')->orderByDesc('id')->first();
    }

    /** True only when a live PPP session exists on the router. */
    public function isOnline(): bool
    {
        return (bool) $this->pppAccount?->is_active_session;
    }

    /** Days until the next bill, or null when the date has passed. */
    public function daysUntilNextBill(): ?int
    {
        if (! $this->next_bill_date) {
            return null;
        }
        $days = now()->startOfDay()->diffInDays($this->next_bill_date->startOfDay(), false);

        return $days >= 0 ? (int) $days : null;
    }

    public function daysUntilSuspension(): ?int
    {
        if (! $this->next_suspension_date) {
            return null;
        }
        $days = now()->startOfDay()->diffInDays($this->next_suspension_date->startOfDay(), false);

        return $days >= 0 ? (int) $days : null;
    }

    public function paymentStatusLabel(): string
    {
        $invoice = $this->latestInvoice();
        if (! $invoice) {
            return 'No Invoice';
        }

        return $invoice->statusLabel();
    }

    public function internetStatusLabel(): string
    {
        if ($this->status === 'archived') {
            return 'Archived';
        }
        if ($this->router && ! $this->router->isOnline()) {
            return 'Router Unavailable';
        }
        if ($this->internet_status === 'suspended') {
            return 'Suspended';
        }
        if ($this->isOnline()) {
            return 'Active';
        }

        return 'Offline';
    }
}
