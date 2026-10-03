<?php

declare(strict_types=1);

namespace App\Enums;

enum RoadStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Maintained = 'maintained';
    case Archived = 'archived';
}
