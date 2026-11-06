<?php

namespace App\Exceptions;

class PaymentGatewayException extends BillingPaymentException
{
    public function __construct()
    {
        parent::__construct('Não foi possível processar o pagamento. Tente novamente mais tarde.');
    }

    public function httpStatus(): int
    {
        return 502;
    }
}
