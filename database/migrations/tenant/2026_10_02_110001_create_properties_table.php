<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('operation_id')->nullable();

            $table->string('reference');
            $table->string('name');
            $table->string('type');
            $table->string('status');

            $table->text('address')->nullable();
            $table->text('description')->nullable();

            $table->geometry('location', 'point', 4326)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique('reference');

            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->nullOnDelete();

            $table->index('operation_id');
            $table->index('type');
            $table->index('status');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE properties
            ADD CONSTRAINT properties_type_check
            CHECK (
                type IN (
                    'residential',
                    'commercial',
                    'office',
                    'industrial',
                    'mixed',
                    'land',
                    'other'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE properties
            ADD CONSTRAINT properties_status_check
            CHECK (
                status IN (
                    'planned',
                    'under_construction',
                    'available',
                    'occupied',
                    'under_sale',
                    'sold',
                    'archived'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE properties
            ADD CONSTRAINT properties_location_srid_check
            CHECK (
                location IS NULL
                OR ST_SRID(location) = 4326
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
