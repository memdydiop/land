<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'budgets' => ['amount'],
            'budget_items' => [
                'planned_amount',
                'committed_amount',
                'actual_amount',
                'paid_amount',
                'forecast_amount',
            ],
            'quotes' => ['subtotal', 'tax', 'total'],
            'quote_items' => ['unit_price', 'total'],
            'contracts' => ['amount'],
            'contract_items' => ['unit_price', 'total'],
            'expenses' => ['amount'],
            'invoices' => ['subtotal', 'tax', 'total'],
            'invoice_items' => ['unit_price', 'total'],
            'payments' => ['amount'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if ($table === 'budget_items' && in_array($column, [
                    'committed_amount',
                    'actual_amount',
                    'paid_amount',
                    'forecast_amount',
                ], true)) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE NUMERIC(20,4) USING "%s"::numeric(20,4)',
                    $table,
                    $column,
                    $column
                ));
            }
        }

        foreach ([
            'quote_items',
            'invoice_items',
        ] as $table) {
            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "quantity" TYPE NUMERIC(20,6) USING "quantity"::numeric(20,6)',
                $table
            ));

            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "tax_rate" TYPE NUMERIC(12,6) USING "tax_rate"::numeric(12,6)',
                $table
            ));
        }

        DB::statement(
            'ALTER TABLE budgets ADD CONSTRAINT budgets_currency_xof_check CHECK (currency = \'XOF\')'
        );

        DB::statement(
            'ALTER TABLE quotes ADD CONSTRAINT quotes_currency_xof_check CHECK (currency = \'XOF\')'
        );

        DB::statement(
            'ALTER TABLE contracts ADD CONSTRAINT contracts_currency_xof_check CHECK (currency = \'XOF\')'
        );

        DB::statement(
            'ALTER TABLE expenses ADD CONSTRAINT expenses_currency_xof_check CHECK (currency = \'XOF\')'
        );

        DB::statement(
            'ALTER TABLE invoices ADD CONSTRAINT invoices_currency_xof_check CHECK (currency = \'XOF\')'
        );

        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_currency_xof_check CHECK (currency = \'XOF\')'
        );
    }

    public function down(): void
    {
        foreach ([
            'payments',
            'invoices',
            'expenses',
            'contract_items',
            'contracts',
            'quote_items',
            'quotes',
            'budget_items',
            'budgets',
        ] as $table) {
            DB::statement(sprintf(
                'ALTER TABLE "%s" DROP CONSTRAINT IF EXISTS "%s_currency_xof_check"',
                $table,
                $table
            ));
        }
    }
};
