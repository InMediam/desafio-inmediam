<?php

namespace Database\Factories;

use App\Models\CreditCard;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCard>
 */
class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'card_holder_name' => strtoupper(fake('pt_BR')->name()),
            'card_last_four' => fake()->numerify('####'),
            'card_brand' => fake()->randomElement(['VISA', 'MASTERCARD', 'ELO']),
            'card_token' => fake()->unique()->uuid(),
        ];
    }
}
