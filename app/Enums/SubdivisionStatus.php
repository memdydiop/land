<?php

declare(strict_types=1);

namespace App\Enums;

enum SubdivisionStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Completed = 'completed';
    case Archived = 'archived';
}
