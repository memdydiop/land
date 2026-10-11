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

            $table->ulid('operation_id');

            $table->string('reference');
            $table->string('name');
            $table->string('status');

            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->decimal('area', 15, 2)->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');
            $table->unique(
                ['operation_id', 'id'],
                'subdivisions_operation_id_id_unique',
            );

            // A subdivision belongs strictly to one operation.
            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('operation_id');
            $table->index('status');
        });

        Schema::create('subdivision_lands', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('subdivision_id');
            $table->ulid('operation_id');
            $table->ulid('land_id');
            $table->timestampsTz();

            $table->unique(['subdivision_id', 'land_id']);

            $table->foreign('subdivision_id')
                ->references('id')->on('subdivisions')->cascadeOnDelete();
            $table->foreign('land_id')
                ->references('id')->on('lands')->restrictOnDelete();

            $table->foreign(
                ['operation_id', 'subdivision_id'],
                'subdivision_lands_operation_subdivision_fk',
            )->references(['operation_id', 'id'])
                ->on('subdivisions')->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreign(
                ['operation_id', 'land_id'],
                'subdivision_lands_operation_land_fk',
            )->references(['operation_id', 'land_id'])
                ->on('operation_lands')->cascadeOnUpdate()->restrictOnDelete();

            $table->index('land_id');
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
        DB::statement("
            ALTER TABLE subdivisions
            ADD CONSTRAINT subdivisions_boundary_valid_check
            CHECK (
                boundary IS NULL
                OR ST_IsValid(boundary)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subdivision_lands');
        Schema::dropIfExists('subdivisions');
    }
};