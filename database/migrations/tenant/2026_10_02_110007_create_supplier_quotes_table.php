<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('supplier_party_id');
            $table->ulid('purchase_request_id')->nullable();
            $table->string('reference');
            $table->string('status');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2);
            $table->decimal('total', 15, 2);
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('supplier_party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->nullOnDelete();

            $table->index('supplier_party_id');
            $table->index('purchase_request_id');
            $table->index('status');
            $table->index('quote_date');
        });

        DB::statement("ALTER TABLE supplier_quotes ADD CONSTRAINT supplier_quotes_status_check CHECK (status IN ('received','under_review','accepted','rejected','expired'))");
        DB::statement("ALTER TABLE supplier_quotes ADD CONSTRAINT supplier_quotes_amounts_check CHECK (subtotal >= 0 AND tax >= 0 AND total >= 0)");
        DB::statement("ALTER TABLE supplier_quotes ADD CONSTRAINT supplier_quotes_valid_until_check CHECK (valid_until IS NULL OR valid_until >= quote_date)");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quotes');
    }
};
