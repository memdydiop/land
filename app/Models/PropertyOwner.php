<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PropertyOwner extends Model
{
    use HasFactory;

    protected $table = 'property_owners';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $owner): void {
            $owner->id ??= (string) Str::ulid();
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
