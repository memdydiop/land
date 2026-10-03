<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentVersionStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
