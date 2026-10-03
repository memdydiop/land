<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LandStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Land extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'lands';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => LandStatus::class,
            'area' => 'decimal:2',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_party_id');
    }

    public function landOperations(): BelongsToMany
    {
        return $this->belongsToMany(
            LandOperation::class,
            'land_operation_lands',
            'land_id',
            'land_operation_id',
        );
    }

    public function landOperationLinks(): HasMany
    {
        return $this->hasMany(LandOperationLand::class);
    }
}
