<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGateway extends Model
{
    protected $table = 'customer_gateways';

    protected $fillable = [
        'customer_id',
        'gateway',
        'external_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
