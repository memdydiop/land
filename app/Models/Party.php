<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartyType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'parties';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
            'metadata' => 'array',
        ];
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'id');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(Contact::class, 'id', 'id');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(
            PartyRelationship::class,
            'party_id',
            'id',
        );
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(
            PartyRelationship::class,
            'related_party_id',
            'id',
        );
    }
}
