<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkSituationItem extends Model
{
    use HasFactory;

    protected $table = 'work_situation_items';

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
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:4',
            'progress_percentage' => 'decimal:4',
        ];
    }

    public function workSituation(): BelongsTo
    {
        return $this->belongsTo(WorkSituation::class);
    }
}
