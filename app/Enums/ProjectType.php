<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectType: string
{
    case Construction = 'construction';
    case CivilEngineering = 'civil_engineering';
    case Development = 'development';
    case LandDevelopment = 'land_development';
    case RealEstate = 'real_estate';
    case Infrastructure = 'infrastructure';
    case Other = 'other';
}
