<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;
    
    protected $fillable = ['billing_id', 'credit_card_id', 'amount_paid', 'status', 'paid_at'];

    protected $hidden = ['billing_id', 'credit_card_id'];

    protected $casts = [
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
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
