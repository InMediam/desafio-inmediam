<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $hidden = [
        'billing_id',
        'credit_card_id',
        'created_at',
        'updated_at',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }
}
