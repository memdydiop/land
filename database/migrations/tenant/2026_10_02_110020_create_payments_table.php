<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('invoice_id');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('status');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();

            $table->index('invoice_id');
            $table->index('status');
            $table->index('method');
            $table->index('paid_at');
            $table->index('reference');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending','confirmed','failed','reversed','cancelled'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash','bank_transfer','mobile_money','card','cheque','other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
