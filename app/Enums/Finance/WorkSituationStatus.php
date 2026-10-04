<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum WorkSituationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
