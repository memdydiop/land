<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('supplier_party_id');
            $table->string('reference');
            $table->string('status');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2);
            $table->decimal('total', 15, 2);
            $table->char('currency', 3);
            $table->ulid('project_id')->nullable();
            $table->ulid('created_by');
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('supplier_party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('supplier_party_id');
            $table->index('project_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('order_date');
        });

        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft','submitted','approved','ordered','partially_received','received','cancelled'))");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_amounts_check CHECK (subtotal >= 0 AND tax >= 0 AND total >= 0)");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_expected_date_check CHECK (expected_date IS NULL OR expected_date >= order_date)");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
