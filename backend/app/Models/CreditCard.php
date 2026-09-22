<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    protected $fillable = [
        'customer_id',
        'card_holder_name',
        'card_last_four',
        'card_brand',
        'card_token',
        'card_token_hash',
    ];

    protected $hidden = [
        'card_token',
        'card_token_hash',
    ];

    protected $casts = [
        'card_token' => 'encrypted',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function tokenHash(string $token): string
    {
        return hash_hmac(
            'sha256',
            $token,
            (string) config('app.key')
        );
    }
}
