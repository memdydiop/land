<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('credit_note_id');
            $table->text('description');
            $table->decimal('quantity', 20, 6);
            $table->string('unit', 30);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('tax_rate', 12, 6);
            $table->decimal('total', 20, 4);
            $table->timestampsTz();

            $table->foreign('credit_note_id')
                ->references('id')
                ->on('credit_notes')
                ->cascadeOnDelete();

            $table->index('credit_note_id');
        });

        DB::statement("
            ALTER TABLE credit_note_items
            ADD CONSTRAINT credit_note_items_quantity_check
            CHECK (quantity > 0)
        ");

        DB::statement("
            ALTER TABLE credit_note_items
            ADD CONSTRAINT credit_note_items_unit_price_check
            CHECK (unit_price >= 0)
        ");

        DB::statement("
            ALTER TABLE credit_note_items
            ADD CONSTRAINT credit_note_items_tax_rate_check
            CHECK (tax_rate BETWEEN 0 AND 100)
        ");

        DB::statement("
            ALTER TABLE credit_note_items
            ADD CONSTRAINT credit_note_items_total_check
            CHECK (total >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
