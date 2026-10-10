<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->ulid('reservation_id')->nullable()->after('id');
            $table->string('direction', 20)->default('incoming')->after('amount');
            $table->string('type', 30)->default('invoice_payment')->after('direction');
            $table->ulid('refund_of')->nullable()->after('reference');

            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->restrictOnDelete();

            $table->foreign('refund_of')
                ->references('id')
                ->on('payments')
                ->restrictOnDelete();

            $table->index(['reservation_id', 'status']);
            $table->index(['direction', 'type', 'status']);
            $table->index('refund_of');
        });

        /*
         * Alignement avec le reste du Financial Core TERRA :
         * Reservation / Sale / Invoice utilisent déjà numeric(20,4).
         */
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN amount TYPE NUMERIC(20,4)
            USING amount::NUMERIC(20,4)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_direction_check
            CHECK (direction IN ('incoming', 'outgoing'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_type_check
            CHECK (
                type IN (
                    'deposit',
                    'invoice_payment',
                    'installment',
                    'refund',
                    'other'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_refund_reference_check
            CHECK (
                refund_of IS NULL
                OR (direction = 'outgoing' AND type = 'refund')
            )
            SQL);

        /*
         * Contract -> Sale
         *
         * Un contrat rattaché à une Sale doit porter le même acheteur
         * et la même devise.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_contract_sale()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                sale_record RECORD;
            BEGIN
                IF NEW.sale_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT buyer_party_id, currency
                INTO sale_record
                FROM sales
                WHERE id = NEW.sale_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Sale % does not exist.',
                        NEW.sale_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.party_id <> sale_record.buyer_party_id THEN
                    RAISE EXCEPTION
                        'Contract party must match sale buyer.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.currency <> sale_record.currency THEN
                    RAISE EXCEPTION
                        'Contract currency must match sale currency.'
                        USING ERRCODE = '23514';
                END IF;

                IF TG_OP = 'UPDATE'
                   AND EXISTS (
                       SELECT 1
                       FROM invoices
                       WHERE contract_id = NEW.id
                   )
                   AND (
                       NEW.sale_id IS DISTINCT FROM OLD.sale_id
                       OR NEW.party_id IS DISTINCT FROM OLD.party_id
                       OR NEW.currency IS DISTINCT FROM OLD.currency
                   )
                THEN
                    RAISE EXCEPTION
                        'Contract % has invoices and its commercial identity is frozen.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_contract_sale_trigger
            BEFORE INSERT OR UPDATE OF sale_id, party_id, currency
            ON contracts
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_contract_sale()
            SQL);

        /*
         * Invoice -> Contract
         *
         * Le client facturé et la devise doivent correspondre au contrat.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_invoice_contract()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                contract_record RECORD;
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND EXISTS (
                       SELECT 1
                       FROM payment_allocations
                       WHERE invoice_id = NEW.id
                   )
                   AND (
                       NEW.contract_id IS DISTINCT FROM OLD.contract_id
                       OR NEW.client_party_id IS DISTINCT FROM OLD.client_party_id
                       OR NEW.currency IS DISTINCT FROM OLD.currency
                   )
                THEN
                    RAISE EXCEPTION
                        'Invoice % has payment allocations and its commercial identity is frozen.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                IF NEW.contract_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT party_id, currency
                INTO contract_record
                FROM contracts
                WHERE id = NEW.contract_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Contract % does not exist.',
                        NEW.contract_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.client_party_id <> contract_record.party_id THEN
                    RAISE EXCEPTION
                        'Invoice client must match contract party.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.currency <> contract_record.currency THEN
                    RAISE EXCEPTION
                        'Invoice currency must match contract currency.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_invoice_contract_trigger
            BEFORE INSERT OR UPDATE OF contract_id, client_party_id, currency
            ON invoices
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_invoice_contract()
            SQL);

        /*
         * Payment
         *
         * - cohérence devise Reservation
         * - protection de l'identité financière après allocation
         * - remboursement référencé vers un paiement entrant confirmé
         * - aucun remboursement supérieur au montant initial
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_payment_financial()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                reservation_currency CHAR(3);
                original_payment RECORD;
                refunded_amount NUMERIC(20,4);
            BEGIN
                IF NEW.reservation_id IS NOT NULL THEN
                    SELECT currency
                    INTO reservation_currency
                    FROM reservations
                    WHERE id = NEW.reservation_id;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION
                            'Reservation % does not exist.',
                            NEW.reservation_id
                            USING ERRCODE = '23503';
                    END IF;

                    IF NEW.currency <> reservation_currency THEN
                        RAISE EXCEPTION
                            'Payment currency must match reservation currency.'
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                IF TG_OP = 'UPDATE'
                   AND (
                       NEW.currency IS DISTINCT FROM OLD.currency
                       OR NEW.direction IS DISTINCT FROM OLD.direction
                       OR NEW.reservation_id IS DISTINCT FROM OLD.reservation_id
                   )
                   AND EXISTS (
                       SELECT 1
                       FROM payment_allocations
                       WHERE payment_id = NEW.id
                   )
                THEN
                    RAISE EXCEPTION
                        'Payment % has allocations and its financial identity is frozen.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                IF NEW.refund_of IS NULL THEN
                    RETURN NEW;
                END IF;

                IF NEW.refund_of = NEW.id THEN
                    RAISE EXCEPTION
                        'A payment cannot refund itself.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT id, amount, currency, direction, type, status
                INTO original_payment
                FROM payments
                WHERE id = NEW.refund_of
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Original payment % does not exist.',
                        NEW.refund_of
                        USING ERRCODE = '23503';
                END IF;

                IF original_payment.direction <> 'incoming'
                   OR original_payment.status <> 'confirmed'
                THEN
                    RAISE EXCEPTION
                        'Only a confirmed incoming payment can be refunded.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.currency <> original_payment.currency THEN
                    RAISE EXCEPTION
                        'Refund currency must match original payment currency.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT COALESCE(SUM(amount), 0)
                INTO refunded_amount
                FROM payments
                WHERE refund_of = NEW.refund_of
                  AND id <> NEW.id
                  AND direction = 'outgoing'
                  AND type = 'refund'
                  AND status IN ('pending', 'confirmed');

                IF refunded_amount + NEW.amount > original_payment.amount THEN
                    RAISE EXCEPTION
                        'Refund amount exceeds the original payment balance.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_payment_financial_trigger
            BEFORE INSERT OR UPDATE OF reservation_id, currency, direction, type, amount, refund_of, status
            ON payments
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_payment_financial()
            SQL);

        /*
         * PaymentAllocation
         *
         * - seulement les paiements entrants
         * - même devise Payment / Invoice
         * - total imputé <= Payment
         * - total imputé <= Invoice
         * - si Payment pointe une Reservation, l'Invoice doit rester
         *   dans la même chaîne Sale -> Reservation
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_payment_allocation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                payment_record RECORD;
                invoice_record RECORD;
                invoice_sale_reservation_id TEXT;
                allocated_payment_amount NUMERIC(20,4);
                allocated_invoice_amount NUMERIC(20,4);
                issued_credit_amount NUMERIC(20,4);
                collectible_invoice_amount NUMERIC(20,4);
            BEGIN
                SELECT id, amount, currency, direction, reservation_id
                INTO payment_record
                FROM payments
                WHERE id = NEW.payment_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Payment % does not exist.',
                        NEW.payment_id
                        USING ERRCODE = '23503';
                END IF;

                SELECT id, total, currency, contract_id
                INTO invoice_record
                FROM invoices
                WHERE id = NEW.invoice_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Invoice % does not exist.',
                        NEW.invoice_id
                        USING ERRCODE = '23503';
                END IF;

                IF payment_record.direction <> 'incoming' THEN
                    RAISE EXCEPTION
                        'Only incoming payments can be allocated to invoices.'
                        USING ERRCODE = '23514';
                END IF;

                IF payment_record.currency <> invoice_record.currency THEN
                    RAISE EXCEPTION
                        'Payment currency must match invoice currency.'
                        USING ERRCODE = '23514';
                END IF;

                IF payment_record.reservation_id IS NOT NULL
                   AND invoice_record.contract_id IS NOT NULL
                THEN
                    SELECT s.reservation_id::text
                    INTO invoice_sale_reservation_id
                    FROM contracts c
                    JOIN sales s ON s.id = c.sale_id
                    WHERE c.id = invoice_record.contract_id;

                    IF invoice_sale_reservation_id IS NOT NULL
                       AND invoice_sale_reservation_id
                           <> payment_record.reservation_id::text
                    THEN
                        RAISE EXCEPTION
                            'Payment reservation must match the reservation behind the invoice contract.'
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                SELECT COALESCE(SUM(amount), 0)
                INTO allocated_payment_amount
                FROM payment_allocations
                WHERE payment_id = NEW.payment_id
                  AND id <> NEW.id;

                IF allocated_payment_amount + NEW.amount
                   > payment_record.amount
                THEN
                    RAISE EXCEPTION
                        'Payment allocations exceed the payment amount.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT COALESCE(SUM(total), 0)
                INTO issued_credit_amount
                FROM credit_notes
                WHERE invoice_id = NEW.invoice_id
                  AND status = 'issued';

                collectible_invoice_amount :=
                    invoice_record.total - issued_credit_amount;

                SELECT COALESCE(SUM(amount), 0)
                INTO allocated_invoice_amount
                FROM payment_allocations
                WHERE invoice_id = NEW.invoice_id
                  AND id <> NEW.id;

                IF allocated_invoice_amount + NEW.amount
                   > collectible_invoice_amount
                THEN
                    RAISE EXCEPTION
                        'Payment allocations exceed the collectible balance of invoice % after issued credit notes.',
                        NEW.invoice_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL
        );

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_payment_allocation_trigger
            BEFORE INSERT OR UPDATE OF payment_id, invoice_id, amount
            ON payment_allocations
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_payment_allocation()
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_allocated_payment_amount()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                allocated_amount NUMERIC(20,4);
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND NEW.amount IS DISTINCT FROM OLD.amount
                   AND NEW.amount < OLD.amount
                THEN
                    SELECT COALESCE(SUM(amount), 0)
                    INTO allocated_amount
                    FROM payment_allocations
                    WHERE payment_id = NEW.id;

                    IF allocated_amount > NEW.amount THEN
                        RAISE EXCEPTION
                            'Payment % amount cannot be reduced below its allocated amount.',
                            NEW.id
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL
        );

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_allocated_payment_amount
            BEFORE UPDATE OF amount
            ON payments
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_allocated_payment_amount()
            SQL
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_credit_note_financial()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                invoice_total NUMERIC(20,4);
                invoice_currency CHAR(3);
                issued_credit_amount NUMERIC(20,4);
                allocated_payment_amount NUMERIC(20,4);
            BEGIN
                SELECT total, currency
                INTO invoice_total, invoice_currency
                FROM invoices
                WHERE id = NEW.invoice_id
                FOR UPDATE;

                IF invoice_total IS NULL THEN
                    RAISE EXCEPTION
                        'Invoice % does not exist.',
                        NEW.invoice_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.currency <> invoice_currency THEN
                    RAISE EXCEPTION
                        'Credit note currency must match invoice currency.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status <> 'issued' THEN
                    RETURN NEW;
                END IF;

                SELECT COALESCE(SUM(total), 0)
                INTO issued_credit_amount
                FROM credit_notes
                WHERE invoice_id = NEW.invoice_id
                  AND status = 'issued'
                  AND id <> NEW.id;

                SELECT COALESCE(SUM(amount), 0)
                INTO allocated_payment_amount
                FROM payment_allocations
                WHERE invoice_id = NEW.invoice_id;

                IF issued_credit_amount
                   + NEW.total
                   + allocated_payment_amount
                   > invoice_total
                THEN
                    RAISE EXCEPTION
                        'Issued credit notes and payment allocations exceed invoice % total.',
                        NEW.invoice_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL
        );

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_credit_note_financial_trigger
            BEFORE INSERT OR UPDATE OF invoice_id, currency, total, status
            ON credit_notes
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_credit_note_financial()
            SQL
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_credit_note_financial_trigger ON credit_notes'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_credit_note_financial()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_protect_allocated_payment_amount ON payments'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_allocated_payment_amount()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_payment_allocation_trigger ON payment_allocations'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_payment_allocation()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_payment_financial_trigger ON payments'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_payment_financial()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_invoice_contract_trigger ON invoices'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_invoice_contract()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_contract_sale_trigger ON contracts'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_contract_sale()');

        DB::statement(
            'ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_refund_reference_check'
        );
        DB::statement(
            'ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_type_check'
        );
        DB::statement(
            'ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_direction_check'
        );

        if (DB::table('payments')->whereRaw('amount <> ROUND(amount, 2)')->exists()) {
            throw new RuntimeException(
                'Rollback impossible: at least one payment amount uses more than 2 decimal places.'
            );
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['refund_of']);
            $table->dropForeign(['reservation_id']);
            $table->dropIndex(['reservation_id', 'status']);
            $table->dropIndex(['direction', 'type', 'status']);
            $table->dropIndex(['refund_of']);
            $table->dropColumn([
                'reservation_id',
                'direction',
                'type',
                'refund_of',
            ]);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN amount TYPE NUMERIC(15,2)
            USING amount::NUMERIC(15,2)
            SQL);
    }
};
