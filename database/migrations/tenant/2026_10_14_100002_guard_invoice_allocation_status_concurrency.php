<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Serialize allocation validation against invoice status changes.
         * Always acquire locks in payment -> invoice order.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_payment_allocation_state()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                payment_status TEXT;
                payment_direction TEXT;
                invoice_status TEXT;
            BEGIN
                SELECT status, direction
                INTO payment_status, payment_direction
                FROM payments
                WHERE id = NEW.payment_id
                FOR UPDATE;

                IF payment_status IS NULL THEN
                    RAISE EXCEPTION
                        'Payment % does not exist.',
                        NEW.payment_id
                        USING ERRCODE = '23503';
                END IF;

                IF payment_status <> 'confirmed'
                   OR payment_direction <> 'incoming'
                THEN
                    RAISE EXCEPTION
                        'Only confirmed incoming payments can be allocated to invoices.'
                        USING ERRCODE = '23514';
                END IF;

                /*
                 * Lock and read the invoice status together. If another
                 * transaction changes the invoice first, this statement
                 * waits and checks the committed row version afterwards.
                 */
                SELECT status
                INTO invoice_status
                FROM invoices
                WHERE id = NEW.invoice_id
                FOR UPDATE;

                IF invoice_status IS NULL THEN
                    RAISE EXCEPTION
                        'Invoice % does not exist.',
                        NEW.invoice_id
                        USING ERRCODE = '23503';
                END IF;

                IF invoice_status IN ('draft', 'cancelled') THEN
                    RAISE EXCEPTION
                        'Invoice % is not collectible in status %.',
                        NEW.invoice_id,
                        invoice_status
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);
    }

    public function down(): void
    {
        /*
         * Restore the previous function definition, which locked the payment
         * but read invoice status without locking the invoice row.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_payment_allocation_state()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                payment_status TEXT;
                payment_direction TEXT;
                invoice_status TEXT;
            BEGIN
                SELECT status, direction
                INTO payment_status, payment_direction
                FROM payments
                WHERE id = NEW.payment_id
                FOR UPDATE;

                IF payment_status IS NULL THEN
                    RAISE EXCEPTION
                        'Payment % does not exist.',
                        NEW.payment_id
                        USING ERRCODE = '23503';
                END IF;

                IF payment_status <> 'confirmed'
                   OR payment_direction <> 'incoming'
                THEN
                    RAISE EXCEPTION
                        'Only confirmed incoming payments can be allocated to invoices.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT status
                INTO invoice_status
                FROM invoices
                WHERE id = NEW.invoice_id;

                IF invoice_status IS NULL THEN
                    RAISE EXCEPTION
                        'Invoice % does not exist.',
                        NEW.invoice_id
                        USING ERRCODE = '23503';
                END IF;

                IF invoice_status IN ('draft', 'cancelled') THEN
                    RAISE EXCEPTION
                        'Invoice % is not collectible in status %.',
                        NEW.invoice_id,
                        invoice_status
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);
    }
};
