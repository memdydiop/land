<?php

declare(strict_types=1);

namespace App\Enums;

enum BudgetStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';
}
