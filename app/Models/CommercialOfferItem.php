<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialOfferItem extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'commercial_offer_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
