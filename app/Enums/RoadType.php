<?php

declare(strict_types=1);

namespace App\Enums;

enum RoadType: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Tertiary = 'tertiary';
    case Access = 'access';
    case Pedestrian = 'pedestrian';
    case Other = 'other';
}
