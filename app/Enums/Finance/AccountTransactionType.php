<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum AccountTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
