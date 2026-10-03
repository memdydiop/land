<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}
