<?php

namespace App\Repositories;

use App\Models\CreditCard;

class CreditCardRepository extends AbstractRepository
{
    public function __construct()
    {
        $this->model = app(CreditCard::class);
    }
}
