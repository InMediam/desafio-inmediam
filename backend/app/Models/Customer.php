<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'document',
        'postal_code',
        'street',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'phone',
    ];

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class, 'customer_id');
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(CustomerGateway::class);
    }
}
