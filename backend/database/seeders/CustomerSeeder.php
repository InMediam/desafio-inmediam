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
            'phone' => '11999999999',
            'postal_code' => '01310100',
            'address_number' => '100',
        ]);

        Customer::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria@email.com',
            'document' => '65577833000',
            'phone' => '21988887777',
            'postal_code' => '30130100',
            'address_number' => '200',
        ]);

        Customer::create([
            'name' => 'Carlos Mendes',
            'email' => 'carlos@email.com',
            'document' => '52998224725',
            'phone' => '41977776666',
            'postal_code' => '80010000',
            'address_number' => '350',
        ]);
    }
}
