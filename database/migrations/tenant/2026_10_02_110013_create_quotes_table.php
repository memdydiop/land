<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('client_party_id');
            $table->ulid('project_id')->nullable();
            $table->string('reference');
            $table->string('status');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 20, 4);
            $table->decimal('tax', 20, 4);
            $table->decimal('total', 20, 4);
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->unique('reference');

            $table->foreign('client_party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();

            $table->index('client_party_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('quote_date');
        });

        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_status_check CHECK (status IN ('draft','sent','viewed','accepted','rejected','expired','cancelled'))");
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_amounts_check CHECK (subtotal >= 0 AND tax >= 0 AND total >= 0)");
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_valid_until_check CHECK (valid_until IS NULL OR valid_until >= quote_date)");
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_currency_xof_check CHECK (currency = 'XOF')");
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
