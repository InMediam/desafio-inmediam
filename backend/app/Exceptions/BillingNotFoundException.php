<?php

namespace App\Exceptions;

class BillingNotFoundException extends BillingPaymentException
{
    public function __construct()
    {
        parent::__construct('Cobrança não encontrada');
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
