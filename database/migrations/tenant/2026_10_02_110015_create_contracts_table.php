<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('party_id');
            $table->ulid('project_id')->nullable();
            $table->string('reference');
            $table->string('title');
            $table->string('type');
            $table->string('status');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();

            $table->index('party_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('type');
        });

        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('draft','pending_signature','active','suspended','completed','terminated','expired'))");
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_amount_check CHECK (amount >= 0)");
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_dates_check CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)");
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
