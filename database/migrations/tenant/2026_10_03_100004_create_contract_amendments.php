<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_amendments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('contract_id');
            $table->string('reference');
            $table->string('type', 50);
            $table->string('status', 30);
            $table->text('description')->nullable();
            $table->decimal('amount_delta', 20, 4)->default(0);
            $table->decimal('new_amount', 20, 4)->nullable();
            $table->date('new_end_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['contract_id', 'reference']);

            $table->foreign('contract_id')
                ->references('id')
                ->on('contracts')
                ->cascadeOnDelete();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['contract_id', 'status']);
            $table->index('effective_date');
        });

        DB::statement("
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_status_check
            CHECK (status IN (
                'draft',
                'pending_approval',
                'approved',
                'rejected',
                'cancelled'
            ))
        ");

        DB::statement("
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_type_check
            CHECK (type IN (
                'amount',
                'duration',
                'scope',
                'other'
            ))
        ");

        DB::statement("
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_new_amount_check
            CHECK (new_amount IS NULL OR new_amount >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
    }
};
