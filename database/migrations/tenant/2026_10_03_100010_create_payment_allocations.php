<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('payment_id');
            $table->ulid('invoice_id');
            $table->decimal('amount', 20, 4);
            $table->timestampsTz();

            $table->unique(['payment_id', 'invoice_id']);

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->restrictOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();

            $table->index(['invoice_id', 'payment_id']);
        });

        DB::statement("
            ALTER TABLE payment_allocations
            ADD CONSTRAINT payment_allocations_amount_check
            CHECK (amount > 0)
        ");

        /*
         * Migration des paiements historiques :
         * chaque ancien payments.invoice_id devient
         * une allocation 1:1.
         */
        $payments = DB::table('payments')
            ->select('id', 'invoice_id', 'amount')
            ->whereNotNull('invoice_id')
            ->get();

        foreach ($payments as $payment) {
            DB::table('payment_allocations')->insert([
                'id' => (string) Str::ulid(),
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
