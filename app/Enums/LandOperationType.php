<?php

declare(strict_types=1);

namespace App\Enums;

enum LandOperationType: string
{
    case Subdivision = 'subdivision';
    case LandDevelopment = 'land_development';
    case Lotissement = 'lotissement';
    case Redevelopment = 'redevelopment';
    case Other = 'other';
}
