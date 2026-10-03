<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParcelStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Parcel extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'parcels';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ParcelStatus::class,
            'area' => 'decimal:2',
            'frontage' => 'decimal:2',
            'depth' => 'decimal:2',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function property(): HasOne
    {
        return $this->hasOne(Property::class);
    }
}
