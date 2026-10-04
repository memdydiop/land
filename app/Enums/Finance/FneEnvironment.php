<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum FneEnvironment: string
{
    case Test = 'test';
    case Production = 'production';
}
