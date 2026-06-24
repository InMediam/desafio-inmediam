<?php

namespace App\Repositories;

use App\Models\Customer;

class CustomerRepository extends AbstractRepository
{
    public function __construct()
    {
        $this->model = app(Customer::class);
    }
}
