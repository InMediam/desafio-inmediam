<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class CreditCard.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $card_holder_name
 * @property string $card_last_four
 * @property string $card_brand
 * @property string $card_token
 *
 * @property Customer $customer
 */
class CreditCard extends Model
{
    protected $fillable = [
        'customer_id',
        'card_holder_name',
        'card_last_four',
        'card_brand',
        'card_token',
    ];

    /**
     * @return BelongsTo<Customer, self>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return !empty($this->card_token);
    }

    /**
     * @return string
     */
    public function maskedCard(): string
    {
        return '**** **** **** ' . $this->card_last_four;
    }
}