<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Ordered = 'ordered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
