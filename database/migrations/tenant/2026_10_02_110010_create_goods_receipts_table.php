<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('purchase_order_id');
            $table->string('reference');
            $table->ulid('received_by');
            $table->timestampTz('received_at');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('purchase_order_id');
            $table->index('received_by');
            $table->index('status');
            $table->index('received_at');
        });

        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status IN ('draft','confirmed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
