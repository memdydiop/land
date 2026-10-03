<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id')->nullable();
            $table->ulid('construction_site_id')->nullable();
            $table->string('name');
            $table->string('status');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('construction_site_id')->references('id')->on('construction_sites')->nullOnDelete();

            $table->index('project_id');
            $table->index('construction_site_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE budgets ADD CONSTRAINT budgets_status_check CHECK (status IN ('draft','active','closed','archived'))");
        DB::statement("ALTER TABLE budgets ADD CONSTRAINT budgets_amount_check CHECK (amount >= 0)");
        DB::statement("ALTER TABLE budgets ADD CONSTRAINT budgets_dates_check CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)");
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
