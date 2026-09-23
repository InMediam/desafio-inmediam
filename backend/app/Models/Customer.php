<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'document',
        'phone',
        'postal_code',
        'address_number',
    ];
}
