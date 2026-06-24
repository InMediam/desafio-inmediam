<?php

namespace App\Enums;

enum BillingStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELED = 'canceled';
    case OVERDUE = 'overdue';
}
