<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandOperationLand extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'land_operation_lands';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    public function landOperation(): BelongsTo
    {
        return $this->belongsTo(LandOperation::class);
    }

    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }
}
