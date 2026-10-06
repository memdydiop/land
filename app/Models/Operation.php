<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperationStatus;
use App\Enums\OperationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'operations';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'status' => OperationStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function lands(): BelongsToMany
    {
        return $this->belongsToMany(
            Land::class,
            'operation_lands',
            'operation_id',
            'land_id',
        );
    }

    public function landLinks(): HasMany
    {
        return $this->hasMany(OperationLand::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function subdivisions(): HasMany
    {
        return $this->hasMany(Subdivision::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
