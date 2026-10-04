<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_items', function (Blueprint $table): void {
            $table->decimal('committed_amount', 20, 4)->default(0)->after('planned_amount');
            $table->decimal('actual_amount', 20, 4)->default(0)->after('committed_amount');
            $table->decimal('paid_amount', 20, 4)->default(0)->after('actual_amount');
            $table->decimal('forecast_amount', 20, 4)->default(0)->after('paid_amount');
        });

        DB::statement(
            'ALTER TABLE budget_items ADD CONSTRAINT budget_items_committed_amount_check CHECK (committed_amount >= 0)'
        );

        DB::statement(
            'ALTER TABLE budget_items ADD CONSTRAINT budget_items_actual_amount_check CHECK (actual_amount >= 0)'
        );

        DB::statement(
            'ALTER TABLE budget_items ADD CONSTRAINT budget_items_paid_amount_check CHECK (paid_amount >= 0)'
        );

        DB::statement(
            'ALTER TABLE budget_items ADD CONSTRAINT budget_items_forecast_amount_check CHECK (forecast_amount >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE budget_items DROP CONSTRAINT IF EXISTS budget_items_committed_amount_check');
        DB::statement('ALTER TABLE budget_items DROP CONSTRAINT IF EXISTS budget_items_actual_amount_check');
        DB::statement('ALTER TABLE budget_items DROP CONSTRAINT IF EXISTS budget_items_paid_amount_check');
        DB::statement('ALTER TABLE budget_items DROP CONSTRAINT IF EXISTS budget_items_forecast_amount_check');

        Schema::table('budget_items', function (Blueprint $table): void {
            $table->dropColumn([
                'committed_amount',
                'actual_amount',
                'paid_amount',
                'forecast_amount',
            ]);
        });
    }
};
