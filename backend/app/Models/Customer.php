<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'document',
        'asaas_customer_id',
        'phone',
        'postal_code',
        'address_number',
    ];

    protected $hidden = ['document', 'email', 'phone', 'postal_code', 'address_number'];
}
