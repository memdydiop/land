<?php

declare(strict_types=1);

namespace App\Enums;

enum LandStatus: string
{
    case Prospect = 'prospect';
    case UnderStudy = 'under_study';
    case Acquired = 'acquired';
    case UnderDevelopment = 'under_development';
    case Developed = 'developed';
    case Sold = 'sold';
    case Archived = 'archived';
}
