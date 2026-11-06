<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::create([
            'name' => 'João da Silva',
            'email' => 'joao@email.com',
            'document' => '45596714003',
            'phone' => '11987654321',
            'postal_code' => '01310100',
            'address_number' => '100',
        ]);

        Customer::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria@email.com',
            'document' => '65577833000',
            'phone' => '21987654321',
            'postal_code' => '20040020',
            'address_number' => '50',
        ]);

        Customer::create([
            'name' => 'Empresa Exemplo Ltda',
            'email' => 'empresa@email.com',
            'document' => '11222333000181',
            'phone' => '11987654322',
            'postal_code' => '04538133',
            'address_number' => '1000',
        ]);
    }
}
