<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum InvoiceType: string
{
    case Sale = 'sale';
    case Advance = 'advance';
    case Progress = 'progress';
    case Final = 'final';
    case Other = 'other';
}
