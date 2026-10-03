<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartyType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(
            Party::class,
            'id',
            'id',
        );
    }
}
