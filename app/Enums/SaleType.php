<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleType: string
{
    case Land = 'land';
    case RealEstate = 'real_estate';
    case Mixed = 'mixed';
    case Other = 'other';
}
