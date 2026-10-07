<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
}
