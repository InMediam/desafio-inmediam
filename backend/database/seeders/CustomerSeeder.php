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
            'phone' => '31999999999',
            'postal_code' => '34000000',
            'address_number' => '100',
        ]);

        Customer::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria@email.com',
            'document' => '65577833000',
            'phone' => '31988888888',
            'postal_code' => '34000001',
            'address_number' => '200',
        ]);
    }
}
