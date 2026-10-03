<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('purchase_order_id');
            $table->text('description');
            $table->decimal('quantity', 15, 3);
            $table->string('unit');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 7, 4);
            $table->decimal('total', 15, 2);
            $table->timestampsTz();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->index('purchase_order_id');
        });

        DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_quantity_check CHECK (quantity > 0)");
        DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_unit_price_check CHECK (unit_price >= 0)");
        DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)");
        DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_total_check CHECK (total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
