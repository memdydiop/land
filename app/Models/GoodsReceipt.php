<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GoodsReceipt extends Model
{
    use HasFactory;

    protected $table = 'goods_receipts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            $receipt->id ??= (string) Str::ulid();
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
