<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'blocks';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BlockStatus::class,
            'area' => 'decimal:2',
        ];
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class);
    }
}
