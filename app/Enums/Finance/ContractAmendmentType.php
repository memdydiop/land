<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum ContractAmendmentType: string
{
    case Amount = 'amount';
    case Duration = 'duration';
    case Scope = 'scope';
    case Other = 'other';
}
