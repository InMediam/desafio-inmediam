<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

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
        'status' => PaymentStatus::class,
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function creditCard()
    {
        return $this->belongsTo(CreditCard::class);
    }
}
