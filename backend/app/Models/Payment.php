<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Payment.
 *
 * @property int $id
 * @property int $billing_id
 * @property int $credit_card_id
 * @property float $amount_paid
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $paid_at
 *
 * @property Billing $billing
 * @property CreditCard $creditCard
 */
class Payment extends Model
{
    protected $fillable = [
        'billing_id',
        'credit_card_id',
        'amount_paid',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Billing, self>
     */
    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    /**
     * @return BelongsTo<CreditCard, self>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}