<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Converted = 'converted';
}
