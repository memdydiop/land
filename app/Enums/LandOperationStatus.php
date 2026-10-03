<?php

declare(strict_types=1);

namespace App\Enums;

enum LandOperationStatus: string
{
    case Draft = 'draft';
    case Study = 'study';
    case Administrative = 'administrative';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}
