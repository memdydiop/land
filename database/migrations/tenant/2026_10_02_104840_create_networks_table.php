<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('networks', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subdivision_id');

            $table->string('type');
            $table->string('name');
            $table->string('status');

            $table->geometry('geometry', 'multilinestring', 4326)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            // A network belongs strictly to one subdivision.
            $table->foreign('subdivision_id')
                ->references('id')
                ->on('subdivisions')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('subdivision_id');
            $table->index('type');
            $table->index('status');
            $table->index(['subdivision_id', 'type']);
        });

        // Domain integrity: network type.
        DB::statement("
            ALTER TABLE networks
            ADD CONSTRAINT networks_type_check
            CHECK (
                type IN (
                    'water',
                    'electricity',
                    'telecom',
                    'drainage',
                    'sewer',
                    'other'
                )
            )
        ");

        // Domain integrity: network status.
        DB::statement("
            ALTER TABLE networks
            ADD CONSTRAINT networks_status_check
            CHECK (
                status IN (
                    'planned',
                    'in_progress',
                    'operational',
                    'suspended',
                    'abandoned'
                )
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE networks
            ADD CONSTRAINT networks_geometry_srid_check
            CHECK (
                geometry IS NULL
                OR ST_SRID(geometry) = 4326
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('networks');
    }
};