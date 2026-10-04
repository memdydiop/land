<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('construction_site_id');

            $table->string('name');
            $table->string('reference');
            $table->text('description')->nullable();

            $table->string('status');

            $table->unsignedSmallInteger('progress')->default(0);

            $table->decimal('budget_amount', 20, 4)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestampsTz();

            // Business reference: unique within the tenant schema.
            $table->unique('reference');

            // A work package belongs strictly to one construction site.
            $table->foreign('construction_site_id')
                ->references('id')
                ->on('construction_sites')
                ->cascadeOnDelete();

            // Query indexes.
            $table->index('construction_site_id');
            $table->index('status');
            $table->index(['construction_site_id', 'status']);
            $table->index('start_date');
            $table->index('end_date');
        });

        // Domain integrity: work package status.
        DB::statement("
            ALTER TABLE work_packages
            ADD CONSTRAINT work_packages_status_check
            CHECK (
                status IN (
                    'planned',
                    'in_progress',
                    'blocked',
                    'completed',
                    'cancelled'
                )
            )
        ");

        // Progress must remain between 0 and 100%.
        DB::statement("
            ALTER TABLE work_packages
            ADD CONSTRAINT work_packages_progress_check
            CHECK (
                progress BETWEEN 0 AND 100
            )
        ");

        // Budget cannot be negative.
        DB::statement("
            ALTER TABLE work_packages
            ADD CONSTRAINT work_packages_budget_amount_check
            CHECK (
                budget_amount IS NULL
                OR budget_amount >= 0
            )
        ");

        // End date cannot precede start date.
        DB::statement("
            ALTER TABLE work_packages
            ADD CONSTRAINT work_packages_dates_check
            CHECK (
                start_date IS NULL
                OR end_date IS NULL
                OR end_date >= start_date
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_packages');
    }
};