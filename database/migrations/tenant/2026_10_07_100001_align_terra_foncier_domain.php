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
        DB::statement('ALTER TABLE parcels ALTER COLUMN ilot_id DROP NOT NULL');

        DB::statement('ALTER TABLE parcels DROP CONSTRAINT IF EXISTS parcels_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_status_check
            CHECK (
                status IN (
                    'provisional',
                    'registered',
                    'merged',
                    'split',
                    'cancelled',
                    'archived'
                )
            )
            SQL);

        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE properties
            ADD CONSTRAINT properties_type_check
            CHECK (
                type IN (
                    'residential',
                    'commercial',
                    'office',
                    'industrial',
                    'mixed',
                    'other'
                )
            )
            SQL);

        Schema::create('subdivision_lands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('subdivision_id');
            $table->ulid('land_id');
            $table->timestampsTz();

            $table->unique(['subdivision_id', 'land_id']);

            $table->foreign('subdivision_id')
                ->references('id')
                ->on('subdivisions')
                ->cascadeOnDelete();

            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->restrictOnDelete();

            $table->index('land_id');
        });

        foreach (['lands', 'subdivisions', 'ilots', 'parcels', 'buildings'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'boundary')) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s CHECK (boundary IS NULL OR ST_IsValid(boundary))',
                $tableName,
                $tableName.'_boundary_valid_check',
            ));
        }
    }

    public function down(): void
    {
        foreach (['lands', 'subdivisions', 'ilots', 'parcels', 'buildings'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'boundary')) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                $tableName,
                $tableName.'_boundary_valid_check',
            ));
        }

        Schema::dropIfExists('subdivision_lands');

        DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE properties
            ADD CONSTRAINT properties_type_check
            CHECK (
                type IN (
                    'residential',
                    'commercial',
                    'office',
                    'industrial',
                    'mixed',
                    'land',
                    'other'
                )
            )
            SQL);

        DB::statement('ALTER TABLE parcels DROP CONSTRAINT IF EXISTS parcels_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_status_check
            CHECK (
                status IN (
                    'available',
                    'reserved',
                    'under_contract',
                    'sold',
                    'transferred',
                    'blocked',
                    'archived'
                )
            )
            SQL);

        DB::statement('ALTER TABLE parcels ALTER COLUMN ilot_id SET NOT NULL');
    }
};
