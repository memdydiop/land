<?php

declare(strict_types=1);

namespace App\Enums;

enum PropertyType: string
{
    case Residential = 'residential';
    case Commercial = 'commercial';
    case Office = 'office';
    case Industrial = 'industrial';
    case Mixed = 'mixed';
    case Land = 'land';
    case Other = 'other';
}
