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
        Schema::create('parcels', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('block_id');

            $table->string('reference');
            $table->string('parcel_number');

            $table->string('status');

            $table->decimal('area', 15, 2)->nullable();

            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->string('land_use')->nullable();

            $table->decimal('frontage', 15, 2)->nullable();
            $table->decimal('depth', 15, 2)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // Parcel number is unique within its block.
            $table->unique(['block_id', 'parcel_number']);

            // A parcel belongs strictly to one block.
            $table->foreign('block_id')
                ->references('id')
                ->on('blocks')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('block_id');
            $table->index('status');
            $table->index('land_use');
        });

        // Domain integrity: parcel status.
        DB::statement("
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
        ");

        // Area cannot be negative.
        DB::statement("
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
        ");

        // Frontage cannot be negative.
        DB::statement("
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_frontage_check
            CHECK (
                frontage IS NULL
                OR frontage >= 0
            )
        ");

        // Depth cannot be negative.
        DB::statement("
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_depth_check
            CHECK (
                depth IS NULL
                OR depth >= 0
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_boundary_srid_check
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
        Schema::dropIfExists('parcels');
    }
};