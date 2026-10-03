<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConstructionSiteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionSite extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'construction_sites';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ConstructionSiteStatus::class,
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class);
    }

    public function siteLogs(): HasMany
    {
        return $this->hasMany(SiteLog::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }
}
