<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('budget_id');
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('planned_amount', 20, 4);
            $table->decimal('committed_amount', 20, 4)->default(0);
            $table->decimal('actual_amount', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->decimal('forecast_amount', 20, 4)->default(0);
            $table->timestampsTz();

            $table->foreign('budget_id')->references('id')->on('budgets')->cascadeOnDelete();
            $table->index('budget_id');
            $table->index('category');
        });

        DB::statement("ALTER TABLE budget_items ADD CONSTRAINT budget_items_planned_amount_check CHECK (planned_amount >= 0)");
        DB::statement("ALTER TABLE budget_items ADD CONSTRAINT budget_items_committed_amount_check CHECK (committed_amount >= 0)");
        DB::statement("ALTER TABLE budget_items ADD CONSTRAINT budget_items_actual_amount_check CHECK (actual_amount >= 0)");
        DB::statement("ALTER TABLE budget_items ADD CONSTRAINT budget_items_paid_amount_check CHECK (paid_amount >= 0)");
        DB::statement("ALTER TABLE budget_items ADD CONSTRAINT budget_items_forecast_amount_check CHECK (forecast_amount >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
