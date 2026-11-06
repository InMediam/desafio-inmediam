<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['billing_id', 'credit_card_id', 'amount_paid', 'status', 'paid_at', 'asaas_charge_id', 'idempotency_key'];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function creditCard()
    {
        return $this->belongsTo(CreditCard::class);
    }
}
