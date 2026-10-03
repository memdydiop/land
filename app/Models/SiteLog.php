<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteLog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'site_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'workers_count' => 'integer',
        ];
    }

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
