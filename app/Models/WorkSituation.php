<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Finance\WorkSituationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class WorkSituation extends Model
{
    use HasFactory;

    protected $table = 'work_situations';

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
            'status' => WorkSituationStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'previous_cumulative_amount' => 'decimal:4',
            'current_period_amount' => 'decimal:4',
            'cumulative_amount' => 'decimal:4',
            'retention_rate' => 'decimal:6',
            'retention_amount' => 'decimal:4',
            'net_amount' => 'decimal:4',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkSituationItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
