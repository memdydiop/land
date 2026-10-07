<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentScheduleTriggerType: string
{
    case Date = 'date';
    case Milestone = 'milestone';
}
