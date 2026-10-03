<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitType: string
{
    case Apartment = 'apartment';
    case Office = 'office';
    case Shop = 'shop';
    case Warehouse = 'warehouse';
    case House = 'house';
    case Villa = 'villa';
    case Other = 'other';
}
