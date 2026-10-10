<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('quote_id');
            $table->text('description');
            $table->decimal('quantity', 20, 6);
            $table->string('unit');
            $table->decimal('unit_price', 20, 4);
            $table->decimal('tax_rate', 12, 6);
            $table->decimal('total', 20, 4);
            $table->timestampsTz();

            $table->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
            $table->index('quote_id');
        });

        DB::statement("ALTER TABLE quote_items ADD CONSTRAINT quote_items_quantity_check CHECK (quantity > 0)");
        DB::statement("ALTER TABLE quote_items ADD CONSTRAINT quote_items_unit_price_check CHECK (unit_price >= 0)");
        DB::statement("ALTER TABLE quote_items ADD CONSTRAINT quote_items_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)");
        DB::statement("ALTER TABLE quote_items ADD CONSTRAINT quote_items_total_check CHECK (total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
