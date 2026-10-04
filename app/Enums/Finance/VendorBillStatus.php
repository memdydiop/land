<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum VendorBillStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Verified = 'verified';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
