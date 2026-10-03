<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkPackageStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
