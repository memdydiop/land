<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parcel_id')->nullable();
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

            $table->foreign('parcel_id')
                ->references('id')->on('parcels')->nullOnDelete();

            $table->index('parcel_id');
            $table->index('type');
            $table->index('status');
        });

        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_type_check CHECK (type IN ('residential','commercial','office','industrial','mixed','land','other'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_status_check CHECK (status IN ('planned','under_construction','available','occupied','under_sale','sold','archived'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_location_srid_check CHECK (location IS NULL OR ST_SRID(location) = 4326)");
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
