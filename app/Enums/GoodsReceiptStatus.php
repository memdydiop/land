<?php

declare(strict_types=1);

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
