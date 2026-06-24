<?php

namespace App\Services;

use App\Repositories\CreditCardRepository;

class CreditCardService extends AbstractService
{
    public function __construct()
    {
        $this->repository = app(CreditCardRepository::class);
    }
}
