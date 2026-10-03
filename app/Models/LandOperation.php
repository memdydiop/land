<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LandOperationStatus;
use App\Enums\LandOperationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandOperation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'land_operations';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => LandOperationType::class,
            'status' => LandOperationStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lands(): BelongsToMany
    {
        return $this->belongsToMany(
            Land::class,
            'land_operation_lands',
            'land_operation_id',
            'land_id',
        );
    }

    public function landLinks(): HasMany
    {
        return $this->hasMany(LandOperationLand::class);
    }

    public function subdivisions(): HasMany
    {
        return $this->hasMany(Subdivision::class);
    }
}
