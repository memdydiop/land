<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoadStatus;
use App\Enums\RoadType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Road extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'roads';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'road_type' => RoadType::class,
            'status' => RoadStatus::class,
            'width' => 'decimal:2',
            'length' => 'decimal:2',
        ];
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }
}
