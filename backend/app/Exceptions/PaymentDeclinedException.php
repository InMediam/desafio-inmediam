<?php

namespace App\Exceptions;

class PaymentDeclinedException extends BillingPaymentException
{
    public function __construct()
    {
        parent::__construct('Pagamento recusado. Verifique os dados do cartão e tente novamente.');
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
