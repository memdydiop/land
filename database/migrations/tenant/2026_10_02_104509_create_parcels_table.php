<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('ilot_id');

            $table->ulid('land_id')->nullable();

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

            $table->unique('reference');
            $table->unique(['ilot_id', 'parcel_number']);

            $table->foreign('ilot_id')
                ->references('id')
                ->on('ilots')
                ->cascadeOnDelete();

            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->restrictOnDelete();

            $table->index('ilot_id');
            $table->index('land_id');
            $table->index('status');
            $table->index('land_use');
        });

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

        DB::statement(<<<'SQL'
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_frontage_check
            CHECK (
                frontage IS NULL
                OR frontage >= 0
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_depth_check
            CHECK (
                depth IS NULL
                OR depth >= 0
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE parcels
            ADD CONSTRAINT parcels_boundary_srid_check
            CHECK (
                boundary IS NULL
                OR ST_SRID(boundary) = 4326
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
