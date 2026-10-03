<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('client_party_id');
            $table->ulid('project_id')->nullable();
            $table->ulid('contract_id')->nullable();
            $table->string('reference');
            $table->string('invoice_number');
            $table->string('status');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2);
            $table->decimal('total', 15, 2);
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->unique('reference');
            $table->unique('invoice_number');

            $table->foreign('client_party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();

            $table->index('client_party_id');
            $table->index('project_id');
            $table->index('contract_id');
            $table->index('status');
            $table->index('issue_date');
            $table->index('due_date');
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('draft','issued','partially_paid','paid','overdue','cancelled'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_amounts_check CHECK (subtotal >= 0 AND tax >= 0 AND total >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_due_date_check CHECK (due_date IS NULL OR due_date >= issue_date)");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
