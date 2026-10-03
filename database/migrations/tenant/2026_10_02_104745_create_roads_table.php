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
        Schema::create('roads', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subdivision_id');

            $table->string('reference');
            $table->string('name');

            $table->string('road_type');
            $table->string('status');

            $table->decimal('width', 15, 2)->nullable();
            $table->decimal('length', 15, 2)->nullable();

            $table->geometry('geometry', 'multilinestring', 4326)->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // A road belongs strictly to one subdivision.
            $table->foreign('subdivision_id')
                ->references('id')
                ->on('subdivisions')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('subdivision_id');
            $table->index('road_type');
            $table->index('status');
        });

        // Domain integrity: road type.
        DB::statement("
            ALTER TABLE roads
            ADD CONSTRAINT roads_road_type_check
            CHECK (
                road_type IN (
                    'primary',
                    'secondary',
                    'tertiary',
                    'access',
                    'pedestrian',
                    'other'
                )
            )
        ");

        // Domain integrity: road status.
        DB::statement("
            ALTER TABLE roads
            ADD CONSTRAINT roads_status_check
            CHECK (
                status IN (
                    'planned',
                    'in_progress',
                    'completed',
                    'maintained',
                    'archived'
                )
            )
        ");

        // Width cannot be negative.
        DB::statement("
            ALTER TABLE roads
            ADD CONSTRAINT roads_width_check
            CHECK (
                width IS NULL
                OR width >= 0
            )
        ");

        // Length cannot be negative.
        DB::statement("
            ALTER TABLE roads
            ADD CONSTRAINT roads_length_check
            CHECK (
                length IS NULL
                OR length >= 0
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE roads
            ADD CONSTRAINT roads_geometry_srid_check
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
        Schema::dropIfExists('roads');
    }
};