<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierQuoteStatus: string
{
    case Received = 'received';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
