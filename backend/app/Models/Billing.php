<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    protected $fillable = [
        'plan_id',
        'customer_id',
        'amount',
        'status',
        'due_date',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
