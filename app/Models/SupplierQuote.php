<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierQuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupplierQuote extends Model
{
    use HasFactory;

    protected $table = 'supplier_quotes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $quote): void {
            $quote->id ??= (string) Str::ulid();
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SupplierQuoteStatus::class,
            'quote_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:4',
            'tax' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
