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
            $table->ulid('reservation_id')->nullable();
            $table->decimal('amount', 20, 4);
            $table->string('direction', 20)->default('incoming');
            $table->string('type', 30)->default('invoice_payment');
            $table->char('currency', 3);
            $table->string('method');
            $table->string('reference')->nullable();
            $table->ulid('refund_of')->nullable();
            $table->string('status');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();



            $table->index(['reservation_id', 'status']);
            $table->index(['direction', 'type', 'status']);
            $table->index('refund_of');
            $table->index('status');
            $table->index('method');
            $table->index('paid_at');
            $table->index('reference');
        });

        // Ajouter la FK autoréférente après la création de la PK.
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign('refund_of')
                ->references('id')
                ->on('payments')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending','confirmed','failed','reversed','cancelled'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash','bank_transfer','mobile_money','card','cheque','other'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_xof_check CHECK (currency = 'XOF')");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_direction_check CHECK (direction IN ('incoming','outgoing'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_type_check CHECK (type IN ('deposit','invoice_payment','installment','refund','other'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_refund_reference_check CHECK (refund_of IS NULL OR (direction = 'outgoing' AND type = 'refund'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
