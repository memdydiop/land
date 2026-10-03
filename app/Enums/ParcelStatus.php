<?php

declare(strict_types=1);

namespace App\Enums;

enum ParcelStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case UnderContract = 'under_contract';
    case Sold = 'sold';
    case Transferred = 'transferred';
    case Blocked = 'blocked';
    case Archived = 'archived';
}
