<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
            $table->dropIndex(['invoice_id']);
            $table->dropColumn('invoice_id');
        });
    }

    public function down(): void
    {
        /*
         * Restore the legacy invoice_id only when every payment has
         * at most one allocation. If a payment has multiple allocations,
         * silently choosing one invoice would cause data loss.
         */
        $multiAllocatedPayments = DB::table('payment_allocations')
            ->select('payment_id')
            ->groupBy('payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($multiAllocatedPayments) {
            throw new RuntimeException(
                'Rollback impossible: at least one payment has multiple allocations. '
                .'The legacy payments.invoice_id structure cannot represent this safely.'
            );
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->ulid('invoice_id')->nullable()->after('id');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();

            $table->index('invoice_id');
        });

        DB::statement('
            UPDATE payments p
            SET invoice_id = pa.invoice_id
            FROM payment_allocations pa
            WHERE pa.payment_id = p.id
        ');
    }
};
