<?php

namespace App\Services;

use App\Repositories\PaymentRepository;

class PaymentService extends AbstractService
{
    public function __construct()
    {
        $this->repository = app(PaymentRepository::class);
    }
}
