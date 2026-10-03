<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('building_id');
            $table->string('reference');
            $table->string('unit_type');
            $table->integer('floor')->nullable();
            $table->decimal('surface', 15, 2)->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->string('status');
            $table->decimal('rent_amount', 15, 2)->nullable();
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('building_id')
                ->references('id')->on('buildings')->cascadeOnDelete();

            $table->index('building_id');
            $table->index('unit_type');
            $table->index('status');
        });

        DB::statement("ALTER TABLE units ADD CONSTRAINT units_type_check CHECK (unit_type IN ('apartment','office','shop','warehouse','house','villa','other'))");
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_status_check CHECK (status IN ('planned','under_construction','available','reserved','occupied','sold','archived'))");
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_surface_check CHECK (surface IS NULL OR surface >= 0)");
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_rooms_check CHECK (rooms IS NULL OR rooms > 0)");
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_rent_amount_check CHECK (rent_amount IS NULL OR rent_amount >= 0)");
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_sale_price_check CHECK (sale_price IS NULL OR sale_price >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
