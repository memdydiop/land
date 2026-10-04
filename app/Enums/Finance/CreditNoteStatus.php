<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';
}
