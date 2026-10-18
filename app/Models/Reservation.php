<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property ReservationStatus $status
 * @property Carbon|null $reserved_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $deposit_due_date
 */
class Reservation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    use HasUlids;

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
            'agreed_price' => 'decimal:4',
            'deposit_amount' => 'decimal:4',
            'deposit_due_date' => 'date',
        ];
    }

    /** @return BelongsTo<CommercialOffer, $this> */
    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasOne<Sale, $this> */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }
}
