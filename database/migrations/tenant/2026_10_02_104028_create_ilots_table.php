<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ilots', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('subdivision_id');

            $table->string('reference');
            $table->string('name');
            $table->string('status');

            $table->geometry('boundary', 'multipolygon', 4326)->nullable();

            $table->decimal('area', 15, 2)->nullable();

            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('subdivision_id')
                ->references('id')
                ->on('subdivisions')
                ->cascadeOnDelete();

            $table->index('subdivision_id');
            $table->index('status');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ilots
            ADD CONSTRAINT ilots_status_check
            CHECK (
                status IN (
                    'planned',
                    'active',
                    'completed',
                    'archived'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE ilots
            ADD CONSTRAINT ilots_area_check
            CHECK (
                area IS NULL
                OR area >= 0
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE ilots
            ADD CONSTRAINT ilots_boundary_srid_check
            CHECK (
                boundary IS NULL
                OR ST_SRID(boundary) = 4326
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ilots');
    }
};
