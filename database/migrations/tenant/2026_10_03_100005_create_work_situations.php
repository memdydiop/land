<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_situations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('contract_id');
            $table->ulid('project_id')->nullable();
            $table->string('reference');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30);

            $table->decimal('previous_cumulative_amount', 20, 4)->default(0);
            $table->decimal('current_period_amount', 20, 4)->default(0);
            $table->decimal('cumulative_amount', 20, 4)->default(0);

            $table->decimal('retention_rate', 12, 6)->default(0);
            $table->decimal('retention_amount', 20, 4)->default(0);
            $table->decimal('net_amount', 20, 4)->default(0);

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->ulid('approved_by')->nullable();

            $table->timestampsTz();

            $table->unique(['contract_id', 'reference']);

            $table->foreign('contract_id')
                ->references('id')
                ->on('contracts')
                ->restrictOnDelete();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->nullOnDelete();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['contract_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('period_end');
        });

        DB::statement("
            ALTER TABLE work_situations
            ADD CONSTRAINT work_situations_status_check
            CHECK (status IN (
                'draft',
                'submitted',
                'approved',
                'rejected',
                'cancelled'
            ))
        ");

        DB::statement("
            ALTER TABLE work_situations
            ADD CONSTRAINT work_situations_amounts_check
            CHECK (
                previous_cumulative_amount >= 0
                AND current_period_amount >= 0
                AND cumulative_amount >= 0
                AND retention_amount >= 0
                AND net_amount >= 0
            )
        ");

        DB::statement("
            ALTER TABLE work_situations
            ADD CONSTRAINT work_situations_retention_rate_check
            CHECK (retention_rate BETWEEN 0 AND 100)
        ");

        DB::statement("
            ALTER TABLE work_situations
            ADD CONSTRAINT work_situations_dates_check
            CHECK (period_end >= period_start)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('work_situations');
    }
};
