<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::updateOrCreate(
            ['email' => 'joao@email.com'],
            [
                'name' => 'João da Silva',
                'document' => '45596714003',
            ]
        );

        Customer::updateOrCreate(
            ['email' => 'maria@email.com'],
            [
                'name' => 'Maria Oliveira',
                'document' => '65577833000',
            ]
        );
    }
}
