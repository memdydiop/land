<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitStatus: string
{
    case Planned = 'planned';
    case UnderConstruction = 'under_construction';
    case Available = 'available';
    case Reserved = 'reserved';
    case Occupied = 'occupied';
    case Sold = 'sold';
    case Archived = 'archived';
}
