<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentVisibility: string
{
    case Private = 'private';
    case Internal = 'internal';
    case Restricted = 'restricted';
}
