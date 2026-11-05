<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function creditCard()
    {
        return $this->belongsTo(CreditCard::class);
    }
}
