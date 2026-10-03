<?php

declare(strict_types=1);

namespace App\Enums;

enum NetworkType: string
{
    case Water = 'water';
    case Electricity = 'electricity';
    case Telecom = 'telecom';
    case Drainage = 'drainage';
    case Sewer = 'sewer';
    case Other = 'other';
}
