<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('client_party_id');
            $table->ulid('invoice_id');
            $table->string('reference');
            $table->string('credit_note_number');
            $table->string('status', 30);
            $table->date('issue_date');
            $table->timestampTz('issued_at')->nullable();
            $table->text('reason');

            $table->decimal('subtotal', 20, 4);
            $table->decimal('tax', 20, 4);
            $table->decimal('total', 20, 4);
            $table->char('currency', 3);

            $table->timestampTz('immutable_at')->nullable();
            $table->timestampsTz();

            $table->unique('reference');
            $table->unique('credit_note_number');

            $table->foreign('client_party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();

            $table->index(['client_party_id', 'status']);
            $table->index(['invoice_id', 'status']);
            $table->index('issue_date');
        });

        DB::statement("
            ALTER TABLE credit_notes
            ADD CONSTRAINT credit_notes_status_check
            CHECK (status IN ('draft','issued','cancelled'))
        ");

        DB::statement("
            ALTER TABLE credit_notes
            ADD CONSTRAINT credit_notes_amounts_check
            CHECK (subtotal >= 0 AND tax >= 0 AND total >= 0)
        ");

        DB::statement("
            ALTER TABLE credit_notes
            ADD CONSTRAINT credit_notes_currency_xof_check
            CHECK (currency = 'XOF')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
