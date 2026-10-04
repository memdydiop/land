<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Passkeys\Passkey as BasePasskey;

class Passkey extends BasePasskey
{
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;
}
