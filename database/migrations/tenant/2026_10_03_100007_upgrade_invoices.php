<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->ulid('work_situation_id')->nullable()->after('contract_id');
            $table->string('invoice_type', 30)->default('sale')->after('invoice_number');
            $table->smallInteger('numbering_year')->nullable()->after('invoice_type');
            $table->timestampTz('issued_at')->nullable()->after('issue_date');
            $table->decimal('retention_amount', 20, 4)->default(0)->after('tax');
            $table->decimal('net_amount', 20, 4)->default(0)->after('retention_amount');
            $table->timestampTz('immutable_at')->nullable()->after('currency');

            $table->foreign('work_situation_id')
                ->references('id')
                ->on('work_situations')
                ->nullOnDelete();

            $table->index('work_situation_id');
            $table->index(['numbering_year', 'status']);
        });

        DB::statement("
            UPDATE invoices
            SET
                numbering_year = EXTRACT(YEAR FROM issue_date)::smallint,
                net_amount = total,
                retention_amount = 0
            WHERE numbering_year IS NULL
        ");

        DB::statement("
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_invoice_type_check
            CHECK (invoice_type IN (
                'sale',
                'advance',
                'progress',
                'final',
                'other'
            ))
        ");

        DB::statement("
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_retention_check
            CHECK (retention_amount >= 0)
        ");

        DB::statement("
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_net_amount_check
            CHECK (net_amount >= 0)
        ");

        DB::statement("
            ALTER TABLE invoices
            ADD CONSTRAINT invoices_numbering_year_check
            CHECK (
                numbering_year IS NULL
                OR numbering_year BETWEEN 2000 AND 2200
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX invoices_numbering_unique
            ON invoices (numbering_year, invoice_number)
            WHERE numbering_year IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_numbering_unique');

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_invoice_type_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_retention_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_net_amount_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_numbering_year_check');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['work_situation_id']);
            $table->dropIndex(['work_situation_id']);
            $table->dropIndex(['numbering_year', 'status']);

            $table->dropColumn([
                'work_situation_id',
                'invoice_type',
                'numbering_year',
                'issued_at',
                'retention_amount',
                'net_amount',
                'immutable_at',
            ]);
        });
    }
};
