<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id')->nullable();
            $table->ulid('construction_site_id')->nullable();
            $table->ulid('budget_id')->nullable();
            $table->string('category');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->date('expense_date');
            $table->ulid('paid_by')->nullable();
            $table->string('status');
            $table->timestampsTz();

            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('construction_site_id')->references('id')->on('construction_sites')->nullOnDelete();
            $table->foreign('budget_id')->references('id')->on('budgets')->nullOnDelete();
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();

            $table->index('project_id');
            $table->index('construction_site_id');
            $table->index('budget_id');
            $table->index('paid_by');
            $table->index('category');
            $table->index('status');
            $table->index('expense_date');
        });

        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_status_check CHECK (status IN ('draft','submitted','approved','rejected','paid','cancelled'))");
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
