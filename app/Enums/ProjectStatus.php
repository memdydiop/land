<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}
