<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommercialOfferStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialOffer extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'commercial_offers';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CommercialOfferStatus::class,
            'price' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
