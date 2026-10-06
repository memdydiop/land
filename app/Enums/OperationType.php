<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationType: string
{
    case LandDevelopment = 'land_development';
    case RealEstateDevelopment = 'real_estate_development';
    case Construction = 'construction';
    case Infrastructure = 'infrastructure';
    case Mixed = 'mixed';
    case Other = 'other';
}
