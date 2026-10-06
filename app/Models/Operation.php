<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperationStatus;
use App\Enums\OperationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operation extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

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
            'metadata' => 'array',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @deprecated Use projects() because an operation can contain many projects. */
    public function project(): HasMany
    {
        return $this->projects();
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

    public function subdivisions(): HasMany
    {
        return $this->hasMany(Subdivision::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasManyThrough(Reservation::class, Lot::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
