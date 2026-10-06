<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubdivisionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subdivision extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'subdivisions';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SubdivisionStatus::class,
            'area' => 'decimal:2',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    /** @deprecated Use operation(). */
    public function landOperation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function roads(): HasMany
    {
        return $this->hasMany(Road::class);
    }

    public function networks(): HasMany
    {
        return $this->hasMany(Network::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
