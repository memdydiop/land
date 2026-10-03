<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectPhaseStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
}
