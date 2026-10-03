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
        Schema::create('facilities', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subdivision_id');

            $table->string('type');
            $table->string('name');
            $table->string('status');

            $table->geometry('geometry', 'multipolygon', 4326)->nullable();

            $table->decimal('area', 15, 2)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            // A facility belongs strictly to one subdivision.
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

        // Domain integrity: facility area cannot be negative.
        DB::statement("
            ALTER TABLE facilities
            ADD CONSTRAINT facilities_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE facilities
            ADD CONSTRAINT facilities_geometry_srid_check
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
        Schema::dropIfExists('facilities');
    }
};