<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case CONFIRMED = 'confirmed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}
