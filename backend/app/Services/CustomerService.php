<?php

namespace App\Services;

use App\Repositories\CustomerRepository;

class CustomerService extends AbstractService
{
    public function __construct()
    {
        $this->repository = app(CustomerRepository::class);
    }
}
