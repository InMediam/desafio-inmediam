<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'active',
    ];

    public function billings()
    {
        return $this->hasMany(Billing::class);
    }
}
