<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum FinancialAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case Other = 'other';
}
