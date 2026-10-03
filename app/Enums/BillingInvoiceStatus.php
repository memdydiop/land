<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingInvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case PastDue = 'past_due';
    case Void = 'void';
    case Uncollectible = 'uncollectible';
}
