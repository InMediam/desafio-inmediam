<?php

namespace App\Exceptions;

class BillingAlreadyPaidException extends BillingPaymentException
{
    public function __construct()
    {
        parent::__construct('Cobrança já paga');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
