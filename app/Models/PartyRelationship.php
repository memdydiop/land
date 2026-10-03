<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartyRelationshipStatus;
use App\Enums\PartyRelationshipType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyRelationship extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'party_relationships';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PartyRelationshipType::class,
            'status' => PartyRelationshipStatus::class,
            'started_at' => 'date',
            'ended_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(
            Party::class,
            'party_id',
            'id',
        );
    }

    public function relatedParty(): BelongsTo
    {
        return $this->belongsTo(
            Party::class,
            'related_party_id',
            'id',
        );
    }
}
