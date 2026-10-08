<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $unit): void {
            $unit->id ??= (string) Str::ulid();
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_type' => UnitType::class,
            'status' => UnitStatus::class,
            'floor' => 'integer',
            'surface' => 'decimal:2',
            'rooms' => 'integer',
            'rent_amount' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class);
    }

    public function commercialOfferItems(): HasMany
    {
        return $this->hasMany(CommercialOfferItem::class);
    }
}
