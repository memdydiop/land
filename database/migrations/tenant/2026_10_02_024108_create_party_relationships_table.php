<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_relationships', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Semantics: party_id IS <type> OF related_party_id.
            $table->foreignUlid('party_id')
                ->constrained('parties')
                ->restrictOnDelete();

            $table->foreignUlid('related_party_id')
                ->constrained('parties')
                ->restrictOnDelete();

            $table->string('type', 30);
            $table->string('status', 20)->default('active');

            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->index(['party_id', 'type', 'status']);
            $table->index(['related_party_id', 'type', 'status']);
        });

        // Keep this CHECK synchronized with the PartyRelationshipType PHP enum.
        DB::statement(
            "ALTER TABLE party_relationships
             ADD CONSTRAINT party_relationships_type_check
             CHECK (type IN (
                 'client',
                 'prospect',
                 'supplier',
                 'subcontractor',
                 'partner',
                 'owner',
                 'lessee',
                 'employee'
             ))"
        );

        // Keep this CHECK synchronized with the PartyRelationshipStatus PHP enum.
        DB::statement(
            "ALTER TABLE party_relationships
             ADD CONSTRAINT party_relationships_status_check
             CHECK (status IN (
                 'pending',
                 'active',
                 'suspended',
                 'ended'
             ))"
        );

        DB::statement(
            'ALTER TABLE party_relationships
             ADD CONSTRAINT party_relationships_parties_different_check
             CHECK (party_id <> related_party_id)'
        );

        DB::statement(
            'ALTER TABLE party_relationships
             ADD CONSTRAINT party_relationships_dates_check
             CHECK (
                 ended_at IS NULL
                 OR started_at IS NULL
                 OR ended_at >= started_at
             )'
        );

        DB::statement(
            "ALTER TABLE party_relationships
             ADD CONSTRAINT party_relationships_ended_consistency_check
             CHECK (status <> 'ended' OR ended_at IS NOT NULL)"
        );

        // One open relationship for a given directed pair and type.
        // Historical ended relationships remain allowed.
        DB::statement(
            "CREATE UNIQUE INDEX party_relationships_open_unique
             ON party_relationships (party_id, related_party_id, type)
             WHERE status <> 'ended'"
        );

        // 'partner' is symmetric: A partner of B is also B partner of A.
        DB::statement(
            "CREATE UNIQUE INDEX party_relationships_partner_symmetric_unique
             ON party_relationships (
                 LEAST(party_id, related_party_id),
                 GREATEST(party_id, related_party_id)
             )
             WHERE type = 'partner' AND status <> 'ended'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('party_relationships');
    }
};
