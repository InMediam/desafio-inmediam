<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardFactory extends Factory
{
    /**
     * Definição padrão para persistência no Banco de Dados.
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(), // Cria um cliente automaticamente se não for passado
            'card_holder_name' => strtoupper($this->faker->name()), // Nome em maiúsculo (padrão de cartões)
            'card_last_four' => $this->faker->numerify('####'), // Gera 4 dígitos finais aleatórios
            'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'elo', 'amex']),
            'card_token' => 'tok_' . $this->faker->md5(), // Simula o token retornado pelo Asaas
        ];
    }

    /**
     * MOCK DE REQUEST: Gera os dados brutos para simular o formulário.
     * POST /api/billing/{id}/pay
     */
    public function requestPayload(): array
    {
        return [
            'card_number' => $this->faker->creditCardNumber(),
            'card_holder_name' => strtoupper($this->faker->name()),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+5 years')->format('m/y'),
            'cvv' => '123'
        ];
    }
}