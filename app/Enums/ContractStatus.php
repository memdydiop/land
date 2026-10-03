<?php

declare(strict_types=1);

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case PendingSignature = 'pending_signature';
    case Active = 'active';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Terminated = 'terminated';
    case Expired = 'expired';
}
