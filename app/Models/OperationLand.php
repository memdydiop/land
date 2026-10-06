<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationLand extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'operation_lands';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }
}
