<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository extends AbstractRepository
{
    public function __construct()
    {
        $this->model = app(Payment::class);
    }
}
