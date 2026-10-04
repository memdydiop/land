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
        Schema::create('lands', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('reference');
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status');

            $table->decimal('area', 15, 2)->nullable();

            $table->geometry('location', 'point', 4326)->nullable();
            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->ulid('owner_party_id')->nullable();

            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 20, 4)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // Owner is an optional business reference.
            $table->foreign('owner_party_id')
                ->references('id')
                ->on('parties')
                ->nullOnDelete();

            // Query indexes.
            $table->index('status');
            $table->index('owner_party_id');
            $table->index('acquisition_date');
        });

        // Domain integrity: land status.
        DB::statement("
            ALTER TABLE lands
            ADD CONSTRAINT lands_status_check
            CHECK (
                status IN (
                    'prospect',
                    'under_study',
                    'acquired',
                    'under_development',
                    'developed',
                    'sold',
                    'archived'
                )
            )
        ");

        // Land area cannot be negative.
        DB::statement("
            ALTER TABLE lands
            ADD CONSTRAINT lands_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
        ");

        // Acquisition cost cannot be negative.
        DB::statement("
            ALTER TABLE lands
            ADD CONSTRAINT lands_acquisition_cost_check
            CHECK (
                acquisition_cost IS NULL
                OR acquisition_cost >= 0
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE lands
            ADD CONSTRAINT lands_location_srid_check
            CHECK (
                location IS NULL
                OR ST_SRID(location) = 4326
            )
        ");

        DB::statement("
            ALTER TABLE lands
            ADD CONSTRAINT lands_boundary_srid_check
            CHECK (
                boundary IS NULL
                OR ST_SRID(boundary) = 4326
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lands');
    }
};