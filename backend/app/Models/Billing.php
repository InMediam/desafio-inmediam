<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Billing extends Model
{
    protected $fillable = [
        'uuid',
        'plan_id',
        'customer_id',
        'amount',
        'status',
        'due_date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Billing $billing) {
            if (empty($billing->uuid)) {
                $billing->uuid = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
