<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NetworkStatus;
use App\Enums\NetworkType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Network extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'networks';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => NetworkType::class,
            'status' => NetworkStatus::class,
            'metadata' => 'array',
        ];
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }
}
