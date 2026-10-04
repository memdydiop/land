<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum FneInvoiceSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Certified = 'certified';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
