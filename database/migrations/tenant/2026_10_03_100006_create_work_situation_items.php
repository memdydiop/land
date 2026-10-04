<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_situation_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('work_situation_id');
            $table->text('description');
            $table->decimal('quantity', 20, 6);
            $table->string('unit', 30);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->decimal('progress_percentage', 7, 4)->default(0);
            $table->timestampsTz();

            $table->foreign('work_situation_id')
                ->references('id')
                ->on('work_situations')
                ->cascadeOnDelete();

            $table->index('work_situation_id');
        });

        DB::statement("
            ALTER TABLE work_situation_items
            ADD CONSTRAINT work_situation_items_quantity_check
            CHECK (quantity > 0)
        ");

        DB::statement("
            ALTER TABLE work_situation_items
            ADD CONSTRAINT work_situation_items_unit_price_check
            CHECK (unit_price >= 0)
        ");

        DB::statement("
            ALTER TABLE work_situation_items
            ADD CONSTRAINT work_situation_items_amount_check
            CHECK (amount >= 0)
        ");

        DB::statement("
            ALTER TABLE work_situation_items
            ADD CONSTRAINT work_situation_items_progress_check
            CHECK (progress_percentage BETWEEN 0 AND 100)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('work_situation_items');
    }
};
