<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $table = 'purchase_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->id ??= (string) Str::ulid();
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function supplierQuotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class);
    }
}
