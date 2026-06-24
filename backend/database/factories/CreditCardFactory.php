<?php

namespace Database\Factories;

use App\Models\CreditCard;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'card_holder_name' => fake()->name(),
            'card_last_four' => fake()->numerify('####'),
            'card_brand' => fake()->randomElement(['Visa', 'Mastercard', 'Amex']),
            'card_token' => fake()->uuid(),
        ];
    }
}
