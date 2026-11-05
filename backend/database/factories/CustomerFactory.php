<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake('pt_BR')->name(),
            'email' => fake()->unique()->safeEmail(),
            'document' => fake('pt_BR')->unique()->cpf(false),
        ];
    }
}
