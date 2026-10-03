<?php

declare(strict_types=1);

namespace App\Enums;

enum ConstructionSiteStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
