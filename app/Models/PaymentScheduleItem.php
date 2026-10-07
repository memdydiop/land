<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Finance\PaymentScheduleTriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentScheduleItem extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'payment_schedule_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trigger_type' => PaymentScheduleTriggerType::class,
            'due_on' => 'date',
            'amount' => 'decimal:4',
            'percentage' => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'schedule_item_id');
    }
}
