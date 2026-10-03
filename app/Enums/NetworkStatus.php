<?php

declare(strict_types=1);

namespace App\Enums;

enum NetworkStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Operational = 'operational';
    case Suspended = 'suspended';
    case Abandoned = 'abandoned';
}
