<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('property_id');
            $table->string('name');
            $table->string('reference');
            $table->string('building_type')->nullable();
            $table->unsignedSmallInteger('floors')->nullable();
            $table->unsignedSmallInteger('construction_year')->nullable();
            $table->string('status');
            $table->geometry('footprint', 'multipolygon', 4326)->nullable();
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('property_id')
                ->references('id')->on('properties')->cascadeOnDelete();

            $table->index('property_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE buildings ADD CONSTRAINT buildings_floors_check CHECK (floors IS NULL OR floors > 0)");
        DB::statement("ALTER TABLE buildings ADD CONSTRAINT buildings_construction_year_check CHECK (construction_year IS NULL OR construction_year BETWEEN 1800 AND 2200)");
        DB::statement("ALTER TABLE buildings ADD CONSTRAINT buildings_footprint_srid_check CHECK (footprint IS NULL OR ST_SRID(footprint) = 4326)");
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
