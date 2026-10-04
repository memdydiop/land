<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Finance\ContractAmendmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContractAmendment extends Model
{
    use HasFactory;

    protected $table = 'contract_amendments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ContractAmendmentStatus::class,
            'amount_delta' => 'decimal:4',
            'new_amount' => 'decimal:4',
            'new_end_date' => 'date',
            'effective_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
