<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'reservations';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'amount' => 'decimal:4',
        ];
    }

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
