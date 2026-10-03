<?php

declare(strict_types=1);

namespace App\Enums;

enum PropertyStatus: string
{
    case Planned = 'planned';
    case UnderConstruction = 'under_construction';
    case Available = 'available';
    case Occupied = 'occupied';
    case UnderSale = 'under_sale';
    case Sold = 'sold';
    case Archived = 'archived';
}
