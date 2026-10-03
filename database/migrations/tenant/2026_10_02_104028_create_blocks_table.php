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
        Schema::create('blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('subdivision_id');

            $table->string('reference');
            $table->string('name');
            $table->string('status');

            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->decimal('area', 15, 2)->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // A block belongs strictly to one subdivision.
            $table->foreign('subdivision_id')
                ->references('id')
                ->on('subdivisions')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('subdivision_id');
            $table->index('status');
        });

        // Domain integrity: block status.
        DB::statement("
            ALTER TABLE blocks
            ADD CONSTRAINT blocks_status_check
            CHECK (
                status IN (
                    'planned',
                    'active',
                    'completed',
                    'archived'
                )
            )
        ");

        // Area cannot be negative.
        DB::statement("
            ALTER TABLE blocks
            ADD CONSTRAINT blocks_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
        ");

        // PostGIS geometry integrity.
        DB::statement("
            ALTER TABLE blocks
            ADD CONSTRAINT blocks_boundary_srid_check
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
        Schema::dropIfExists('blocks');
    }
};