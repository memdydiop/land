<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->id ??= (string) Str::ulid();
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:6',
            'total' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
