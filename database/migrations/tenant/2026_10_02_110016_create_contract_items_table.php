<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('contract_id');
            $table->text('description');
            $table->decimal('quantity', 15, 3);
            $table->string('unit');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestampsTz();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->index('contract_id');
        });

        DB::statement("ALTER TABLE contract_items ADD CONSTRAINT contract_items_quantity_check CHECK (quantity > 0)");
        DB::statement("ALTER TABLE contract_items ADD CONSTRAINT contract_items_unit_price_check CHECK (unit_price >= 0)");
        DB::statement("ALTER TABLE contract_items ADD CONSTRAINT contract_items_total_check CHECK (total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
    }
};
