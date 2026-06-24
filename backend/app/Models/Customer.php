<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Customer.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $document
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Billing> $billings
 */
class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'document',
    ];

    /**
     * @return HasMany<Billing, self>
     */
    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    /**
     * @return bool
     */
    public function hasValidDocument(): bool
    {
        return strlen($this->document) === 11;
    }

    /**
     * @return bool
     */
    public function hasValidEmail(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}