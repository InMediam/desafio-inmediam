<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Billing.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $customer_id
 * @property float $amount
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $due_date
 *
 * @property Plan $plan
 * @property Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Payment> $payments
 */
class Billing extends Model
{
    protected $fillable = [
        'plan_id',
        'customer_id',
        'amount',
        'status',
        'due_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    /**
     * @return BelongsTo<Plan, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Customer, self>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Payment, self>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * @return void
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
        ]);
    }

    /**
     * @return void
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
        ]);
    }

    /**
     * @return void
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    /**
     * @return Payment|null
     */
    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest('id')->first();
    }
}