<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingPaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
