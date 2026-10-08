<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LandStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function operations(): BelongsToMany
    {
        return $this->belongsToMany(
            Operation::class,
            'operation_lands',
            'land_id',
            'operation_id',
        );
    }

    public function operationLinks(): HasMany
    {
        return $this->hasMany(OperationLand::class);
    }

    public function commercialOfferItems(): HasMany
    {
        return $this->hasMany(CommercialOfferItem::class);
    }
}
