<?php

namespace App\Exceptions;

use RuntimeException;

abstract class BillingPaymentException extends RuntimeException
{
    abstract public function httpStatus(): int;
}
