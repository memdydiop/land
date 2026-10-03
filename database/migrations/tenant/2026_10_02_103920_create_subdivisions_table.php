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
        Schema::create('subdivisions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('land_operation_id');

            $table->string('reference');
            $table->string('name');
            $table->string('status');

            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->decimal('area', 15, 2)->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // A subdivision belongs strictly to one land operation.
            $table->foreign('land_operation_id')
                ->references('id')
                ->on('land_operations')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('land_operation_id');
            $table->index('status');
        });

        // Domain integrity: subdivision status.
        DB::statement("
            ALTER TABLE subdivisions
            ADD CONSTRAINT subdivisions_status_check
            CHECK (
                status IN (
                    'draft',
                    'in_progress',
                    'approved',
                    'completed',
                    'archived'
                )
            )
        ");

        // Area cannot be negative.
        DB::statement("
            ALTER TABLE subdivisions
            ADD CONSTRAINT subdivisions_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE subdivisions
            ADD CONSTRAINT subdivisions_boundary_srid_check
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
        Schema::dropIfExists('subdivisions');
    }
};