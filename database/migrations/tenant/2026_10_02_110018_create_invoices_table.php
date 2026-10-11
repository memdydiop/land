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
            $table->ulid('work_situation_id')->nullable();
            $table->string('reference');
            $table->string('invoice_number');
            $table->string('invoice_type', 30)->default('sale');
            $table->smallInteger('numbering_year')->nullable();
            $table->string('status');
            $table->date('issue_date');
            $table->timestampTz('issued_at')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4);
            $table->decimal('tax', 20, 4);
            $table->decimal('retention_amount', 20, 4)->default(0);
            $table->decimal('net_amount', 20, 4)->default(0);
            $table->decimal('total', 20, 4);
            $table->char('currency', 3);
            $table->timestampTz('immutable_at')->nullable();
            $table->timestampsTz();

            $table->unique('reference');
            $table->unique('invoice_number');

            $table->foreign('client_party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->restrictOnDelete();
            $table->foreign('work_situation_id')
                ->references('id')
                ->on('work_situations')
                ->nullOnDelete();

            $table->index('client_party_id');
            $table->index('project_id');
            $table->index('contract_id');
            $table->index('work_situation_id');
            $table->index(['numbering_year', 'status']);
            $table->index('status');
            $table->index('issue_date');
            $table->index('due_date');
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('draft','issued','partially_paid','paid','overdue','cancelled'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_amounts_check CHECK (subtotal >= 0 AND tax >= 0 AND total >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_due_date_check CHECK (due_date IS NULL OR due_date >= issue_date)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_invoice_type_check CHECK (invoice_type IN ('sale','advance','progress','final','other'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_retention_check CHECK (retention_amount >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_net_amount_check CHECK (net_amount >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_numbering_year_check CHECK (numbering_year IS NULL OR numbering_year BETWEEN 2000 AND 2200)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_currency_xof_check CHECK (currency = 'XOF')");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
