<?php

declare(strict_types=1);

namespace App\Enums;

enum ParcelStatus: string
{
    case Provisional = 'provisional';
    case Registered = 'registered';
    case Merged = 'merged';
    case Split = 'split';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}
