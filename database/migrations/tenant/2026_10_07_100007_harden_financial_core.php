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
        // La table reservations est créée après payments.
        // Sa clé étrangère reste donc dans cette migration.
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->restrictOnDelete();
        });

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

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_invoice_total_after_activity()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                allocated_amount NUMERIC(20,4);
                issued_credit_amount NUMERIC(20,4);
            BEGIN
                IF NEW.total IS NOT DISTINCT FROM OLD.total THEN
                    RETURN NEW;
                END IF;

                SELECT COALESCE(SUM(amount), 0)
                INTO allocated_amount
                FROM payment_allocations
                WHERE invoice_id = OLD.id;

                SELECT COALESCE(SUM(total), 0)
                INTO issued_credit_amount
                FROM credit_notes
                WHERE invoice_id = OLD.id
                  AND status = 'issued';

                IF allocated_amount + issued_credit_amount > NEW.total THEN
                    RAISE EXCEPTION
                        'Invoice % total cannot be reduced below allocated payments and issued credit notes.',
                        OLD.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_invoice_total_after_activity_trigger
            BEFORE UPDATE OF total ON invoices
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_invoice_total_after_activity()
            SQL);



        // Invoice lifecycle: enforce transitions and consistency with settlement.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_invoice_lifecycle()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                allocated_amount NUMERIC(20,4);
                issued_credit_amount NUMERIC(20,4);
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.status IS DISTINCT FROM OLD.status THEN
                        IF OLD.status IN ('paid', 'cancelled') THEN
                            RAISE EXCEPTION
                                'Invoice in terminal status % cannot change to %.',
                                OLD.status,
                                NEW.status
                                USING ERRCODE = '55006';
                        ELSIF OLD.status = 'draft'
                              AND NEW.status NOT IN ('issued', 'cancelled')
                        THEN
                            RAISE EXCEPTION
                                'A draft invoice can only be issued or cancelled.'
                                USING ERRCODE = '23514';
                        ELSIF OLD.status = 'issued'
                              AND NEW.status NOT IN (
                                  'partially_paid', 'paid', 'overdue', 'cancelled'
                              )
                        THEN
                            RAISE EXCEPTION
                                'An issued invoice cannot return to draft.'
                                USING ERRCODE = '23514';
                        ELSIF OLD.status = 'partially_paid'
                              AND NEW.status NOT IN ('paid', 'overdue')
                        THEN
                            RAISE EXCEPTION
                                'A partially paid invoice can only become paid or overdue.'
                                USING ERRCODE = '23514';
                        ELSIF OLD.status = 'overdue'
                              AND NEW.status NOT IN (
                                  'partially_paid', 'paid', 'cancelled'
                              )
                        THEN
                            RAISE EXCEPTION
                                'An overdue invoice can only become partially paid, paid or cancelled.'
                                USING ERRCODE = '23514';
                        END IF;
                    END IF;
                END IF;

                SELECT COALESCE(SUM(amount), 0)
                INTO allocated_amount
                FROM payment_allocations
                WHERE invoice_id = NEW.id;

                SELECT COALESCE(SUM(total), 0)
                INTO issued_credit_amount
                FROM credit_notes
                WHERE invoice_id = NEW.id
                  AND status = 'issued';

                IF NEW.status = 'paid'
                   AND allocated_amount + issued_credit_amount < NEW.total
                THEN
                    RAISE EXCEPTION
                        'Invoice % cannot be marked paid while an amount remains collectible.',
                        NEW.id
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status = 'partially_paid'
                   AND (
                       allocated_amount <= 0
                       OR allocated_amount + issued_credit_amount >= NEW.total
                   )
                THEN
                    RAISE EXCEPTION
                        'Invoice % requires an outstanding balance to be partially paid.',
                        NEW.id
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status = 'cancelled'
                   AND (
                       allocated_amount > 0
                       OR issued_credit_amount > 0
                   )
                THEN
                    RAISE EXCEPTION
                        'Invoice % with allocations or issued credit notes cannot be cancelled.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_invoice_lifecycle_trigger
            BEFORE INSERT OR UPDATE OF status, total ON invoices
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_invoice_lifecycle()
            SQL);

        // Once immutable_at is set, freeze document and fiscal content.
        // Operational status may still change under the lifecycle guard.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_immutable_invoice_content()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.immutable_at IS NOT NULL THEN
                        RAISE EXCEPTION
                            'Immutable invoice % cannot be deleted.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;

                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE'
                   AND (
                       OLD.immutable_at IS NOT NULL
                       OR NEW.immutable_at IS NOT NULL
                   )
                THEN
                    IF (
                        OLD.immutable_at IS NOT NULL
                        AND NEW.immutable_at IS DISTINCT FROM OLD.immutable_at
                    ) OR ROW(
                           NEW.client_party_id,
                           NEW.project_id,
                           NEW.contract_id,
                           NEW.work_situation_id,
                           NEW.schedule_item_id,
                           NEW.reference,
                           NEW.invoice_number,
                           NEW.invoice_type,
                           NEW.numbering_year,
                           NEW.issue_date,
                           NEW.issued_at,
                           NEW.due_date,
                           NEW.subtotal,
                           NEW.tax,
                           NEW.retention_amount,
                           NEW.net_amount,
                           NEW.total,
                           NEW.currency
                       ) IS DISTINCT FROM ROW(
                           OLD.client_party_id,
                           OLD.project_id,
                           OLD.contract_id,
                           OLD.work_situation_id,
                           OLD.schedule_item_id,
                           OLD.reference,
                           OLD.invoice_number,
                           OLD.invoice_type,
                           OLD.numbering_year,
                           OLD.issue_date,
                           OLD.issued_at,
                           OLD.due_date,
                           OLD.subtotal,
                           OLD.tax,
                           OLD.retention_amount,
                           OLD.net_amount,
                           OLD.total,
                           OLD.currency
                       )
                    THEN
                        RAISE EXCEPTION
                            'Immutable invoice % content cannot be changed.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_immutable_invoice_content_trigger
            BEFORE UPDATE OR DELETE ON invoices
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_immutable_invoice_content()
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

        // Preserve refund traceability and enforce its lifecycle.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_active_refund_lifecycle()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.direction = 'outgoing' AND OLD.type = 'refund' THEN
                        RAISE EXCEPTION
                            'Refund % cannot be deleted; retain its audit history.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;

                    RETURN OLD;
                END IF;

                IF NEW.direction = 'outgoing'
                   AND NEW.type = 'refund'
                   AND NOT (
                       OLD.direction = 'outgoing'
                       AND OLD.type = 'refund'
                   )
                THEN
                    RAISE EXCEPTION
                        'Payment % cannot be reclassified as a refund; create a dedicated refund record.',
                        OLD.id
                        USING ERRCODE = '55006';
                END IF;

                IF OLD.direction = 'outgoing' AND OLD.type = 'refund' THEN
                    IF ROW(
                        NEW.direction,
                        NEW.type,
                        NEW.refund_of,
                        NEW.currency
                    ) IS DISTINCT FROM ROW(
                        OLD.direction,
                        OLD.type,
                        OLD.refund_of,
                        OLD.currency
                    )
                    THEN
                        RAISE EXCEPTION
                            'Refund % financial identity cannot be reclassified or detached.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;

                    IF (OLD.status <> 'pending' OR NEW.status <> 'pending')
                       AND NEW.amount IS DISTINCT FROM OLD.amount
                    THEN
                        RAISE EXCEPTION
                            'Refund % amount is immutable after leaving pending status.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;

                    IF OLD.status = 'pending'
                       AND NEW.status NOT IN (
                           'pending', 'confirmed', 'failed', 'cancelled'
                       )
                    THEN
                        RAISE EXCEPTION
                            'Pending refund % has an invalid status transition to %.',
                            OLD.id,
                            NEW.status
                            USING ERRCODE = '23514';
                    ELSIF OLD.status = 'confirmed'
                          AND NEW.status NOT IN ('confirmed', 'reversed')
                    THEN
                        RAISE EXCEPTION
                            'Confirmed refund % can only remain confirmed or be reversed.',
                            OLD.id
                            USING ERRCODE = '23514';
                    ELSIF OLD.status IN ('failed', 'cancelled', 'reversed')
                          AND NEW.status IS DISTINCT FROM OLD.status
                    THEN
                        RAISE EXCEPTION
                            'Terminal refund % cannot change status from % to %.',
                            OLD.id,
                            OLD.status,
                            NEW.status
                            USING ERRCODE = '55006';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_active_refund_lifecycle_trigger
            BEFORE UPDATE OR DELETE ON payments
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_active_refund_lifecycle()
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_original_payment_with_refunds()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                active_refund_amount NUMERIC(20,4);
                has_active_refunds BOOLEAN;
            BEGIN
                SELECT EXISTS (
                    SELECT 1
                    FROM payments
                    WHERE refund_of = OLD.id
                      AND direction = 'outgoing'
                      AND type = 'refund'
                      AND status IN ('pending', 'confirmed')
                )
                INTO has_active_refunds;

                IF NOT has_active_refunds THEN
                    RETURN NEW;
                END IF;

                SELECT COALESCE(SUM(amount), 0)
                INTO active_refund_amount
                FROM payments
                WHERE refund_of = OLD.id
                  AND direction = 'outgoing'
                  AND type = 'refund'
                  AND status IN ('pending', 'confirmed');

                IF NEW.direction <> 'incoming' THEN
                    RAISE EXCEPTION
                        'A payment referenced by active refunds must remain incoming.'
                        USING ERRCODE = '55006';
                END IF;

                IF NEW.status <> 'confirmed' THEN
                    RAISE EXCEPTION
                        'A payment referenced by active refunds must remain confirmed.'
                        USING ERRCODE = '55006';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM payments
                    WHERE refund_of = OLD.id
                      AND direction = 'outgoing'
                      AND type = 'refund'
                      AND status IN ('pending', 'confirmed')
                      AND currency IS DISTINCT FROM NEW.currency
                ) THEN
                    RAISE EXCEPTION
                        'Original payment currency must match its active refunds.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.amount < active_refund_amount THEN
                    RAISE EXCEPTION
                        'Original payment amount cannot be less than its active refunds.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_original_payment_with_refunds_trigger
            BEFORE UPDATE OF amount, currency, direction, status ON payments
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_original_payment_with_refunds()
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

        // Protect settled invoices against direct allocation changes.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_paid_invoice_allocation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                old_invoice_status TEXT;
                new_invoice_status TEXT;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF ROW(
                        NEW.payment_id,
                        NEW.invoice_id,
                        NEW.amount
                    ) IS NOT DISTINCT FROM ROW(
                        OLD.payment_id,
                        OLD.invoice_id,
                        OLD.amount
                    ) THEN
                        RETURN NEW;
                    END IF;

                    -- Use the same lock order as allocation validation:
                    -- payments first, then invoices.
                    IF NEW.payment_id IS DISTINCT FROM OLD.payment_id THEN
                        PERFORM id
                        FROM payments
                        WHERE id IN (OLD.payment_id, NEW.payment_id)
                        ORDER BY id
                        FOR UPDATE;
                    ELSE
                        PERFORM id
                        FROM payments
                        WHERE id = OLD.payment_id
                        FOR UPDATE;
                    END IF;

                    IF NEW.invoice_id IS DISTINCT FROM OLD.invoice_id THEN
                        PERFORM id
                        FROM invoices
                        WHERE id IN (OLD.invoice_id, NEW.invoice_id)
                        ORDER BY id
                        FOR UPDATE;
                    ELSE
                        PERFORM id
                        FROM invoices
                        WHERE id = OLD.invoice_id
                        FOR UPDATE;
                    END IF;
                ELSE
                    PERFORM id
                    FROM payments
                    WHERE id = OLD.payment_id
                    FOR UPDATE;

                    PERFORM id
                    FROM invoices
                    WHERE id = OLD.invoice_id
                    FOR UPDATE;
                END IF;

                SELECT status
                INTO old_invoice_status
                FROM invoices
                WHERE id = OLD.invoice_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Invoice % does not exist for allocation %.',
                        OLD.invoice_id,
                        OLD.id
                        USING ERRCODE = '23503';
                END IF;

                IF old_invoice_status = 'paid' THEN
                    RAISE EXCEPTION
                        'Financial allocation % for paid invoice % cannot be modified or deleted.',
                        OLD.id,
                        OLD.invoice_id
                        USING ERRCODE = '55006';
                END IF;

                IF TG_OP = 'UPDATE'
                   AND NEW.invoice_id IS DISTINCT FROM OLD.invoice_id
                THEN
                    SELECT status
                    INTO new_invoice_status
                    FROM invoices
                    WHERE id = NEW.invoice_id;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION
                            'Destination invoice % does not exist.',
                            NEW.invoice_id
                            USING ERRCODE = '23503';
                    END IF;

                    IF new_invoice_status = 'paid' THEN
                        RAISE EXCEPTION
                            'Allocation % cannot be moved to paid invoice %.',
                            OLD.id,
                            NEW.invoice_id
                            USING ERRCODE = '55006';
                    END IF;
                END IF;

                IF old_invoice_status = 'partially_paid' THEN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION
                            'Allocation % cannot be deleted from partially paid invoice %.',
                            OLD.id,
                            OLD.invoice_id
                            USING ERRCODE = '55006';
                    END IF;

                    IF NEW.invoice_id IS DISTINCT FROM OLD.invoice_id
                       OR NEW.amount < OLD.amount
                    THEN
                        RAISE EXCEPTION
                            'Allocation % cannot be reduced or moved from partially paid invoice %.',
                            OLD.id,
                            OLD.invoice_id
                            USING ERRCODE = '55006';
                    END IF;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_paid_invoice_allocation_trigger
            BEFORE UPDATE OR DELETE ON payment_allocations
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_paid_invoice_allocation()
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
                invoice_client_party_id TEXT;
                issued_credit_amount NUMERIC(20,4);
                allocated_payment_amount NUMERIC(20,4);
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.status IS DISTINCT FROM OLD.status THEN
                        IF OLD.status IN ('issued', 'cancelled') THEN
                            RAISE EXCEPTION
                                'Credit note in terminal status % cannot change to %.',
                                OLD.status,
                                NEW.status
                                USING ERRCODE = '55006';
                        ELSIF OLD.status = 'draft'
                              AND NEW.status NOT IN ('issued', 'cancelled')
                        THEN
                            RAISE EXCEPTION
                                'A draft credit note can only be issued or cancelled.'
                                USING ERRCODE = '23514';
                        END IF;
                    END IF;
                END IF;

                IF NEW.status = 'issued' THEN
                    NEW.issued_at := COALESCE(NEW.issued_at, now());
                    NEW.immutable_at := COALESCE(NEW.immutable_at, now());
                END IF;

                SELECT total, currency, client_party_id
                INTO invoice_total, invoice_currency, invoice_client_party_id
                FROM invoices
                WHERE id = NEW.invoice_id
                FOR UPDATE;

                IF invoice_total IS NULL THEN
                    RAISE EXCEPTION
                        'Invoice % does not exist.',
                        NEW.invoice_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.client_party_id IS DISTINCT FROM invoice_client_party_id THEN
                    RAISE EXCEPTION
                        'Credit note client must match invoice % client.',
                        NEW.invoice_id
                        USING ERRCODE = '23514';
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
            BEFORE INSERT OR UPDATE OF invoice_id, client_party_id, currency, total, status
            ON credit_notes
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_credit_note_financial()
            SQL
        );


        // Issued and cancelled credit notes retain their fiscal identity.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_credit_note_content()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status IN ('issued', 'cancelled')
                       OR OLD.immutable_at IS NOT NULL
                    THEN
                        RAISE EXCEPTION
                            'Issued, cancelled or immutable credit note % cannot be deleted.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;

                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    IF OLD.status IN ('issued', 'cancelled')
                       OR OLD.immutable_at IS NOT NULL
                    THEN
                        IF (
                            OLD.immutable_at IS NOT NULL
                            AND NEW.immutable_at IS DISTINCT FROM OLD.immutable_at
                        ) OR ROW(
                            NEW.client_party_id,
                            NEW.invoice_id,
                            NEW.reference,
                            NEW.credit_note_number,
                            NEW.issue_date,
                            NEW.issued_at,
                            NEW.reason,
                            NEW.subtotal,
                            NEW.tax,
                            NEW.total,
                            NEW.currency
                        ) IS DISTINCT FROM ROW(
                            OLD.client_party_id,
                            OLD.invoice_id,
                            OLD.reference,
                            OLD.credit_note_number,
                            OLD.issue_date,
                            OLD.issued_at,
                            OLD.reason,
                            OLD.subtotal,
                            OLD.tax,
                            OLD.total,
                            OLD.currency
                        )
                        THEN
                            RAISE EXCEPTION
                                'Credit note % fiscal content is immutable.',
                                OLD.id
                                USING ERRCODE = '55006';
                        END IF;
                    ELSIF NEW.immutable_at IS NOT NULL
                          OR NEW.status = 'cancelled'
                    THEN
                        IF OLD.status = 'draft'
                           AND NEW.status = 'issued'
                        THEN
                            -- issued_at may be set during issuance, but the
                            -- rest of the fiscal content must remain unchanged.
                            IF ROW(
                                NEW.client_party_id,
                                NEW.invoice_id,
                                NEW.reference,
                                NEW.credit_note_number,
                                NEW.issue_date,
                                NEW.reason,
                                NEW.subtotal,
                                NEW.tax,
                                NEW.total,
                                NEW.currency
                            ) IS DISTINCT FROM ROW(
                                OLD.client_party_id,
                                OLD.invoice_id,
                                OLD.reference,
                                OLD.credit_note_number,
                                OLD.issue_date,
                                OLD.reason,
                                OLD.subtotal,
                                OLD.tax,
                                OLD.total,
                                OLD.currency
                            )
                            THEN
                                RAISE EXCEPTION
                                    'Credit note % content cannot change during issuance.',
                                    OLD.id
                                    USING ERRCODE = '55006';
                            END IF;
                        ELSIF ROW(
                            NEW.client_party_id,
                            NEW.invoice_id,
                            NEW.reference,
                            NEW.credit_note_number,
                            NEW.issue_date,
                            NEW.issued_at,
                            NEW.reason,
                            NEW.subtotal,
                            NEW.tax,
                            NEW.total,
                            NEW.currency
                        ) IS DISTINCT FROM ROW(
                            OLD.client_party_id,
                            OLD.invoice_id,
                            OLD.reference,
                            OLD.credit_note_number,
                            OLD.issue_date,
                            OLD.issued_at,
                            OLD.reason,
                            OLD.subtotal,
                            OLD.tax,
                            OLD.total,
                            OLD.currency
                        )
                        THEN
                            RAISE EXCEPTION
                                'Immutable credit note % content cannot change.',
                                OLD.id
                                USING ERRCODE = '55006';
                        END IF;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_z_protect_credit_note_content_trigger
            BEFORE UPDATE OR DELETE ON credit_notes
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_credit_note_content()
            SQL);

        // A credit note's detail lines cannot be changed once its parent
        // is issued, cancelled or explicitly marked immutable.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_credit_note_item_content()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                target_credit_note_id TEXT;
                note_status TEXT;
                note_immutable_at TIMESTAMPTZ;
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND NEW.credit_note_id IS DISTINCT FROM OLD.credit_note_id
                THEN
                    RAISE EXCEPTION
                        'A credit note item cannot be moved to another credit note.'
                        USING ERRCODE = '55006';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    target_credit_note_id := OLD.credit_note_id::text;
                ELSE
                    target_credit_note_id := NEW.credit_note_id::text;
                END IF;

                SELECT status, immutable_at
                INTO note_status, note_immutable_at
                FROM credit_notes
                WHERE id = target_credit_note_id
                FOR UPDATE;

                -- During a permitted cascade from a deletable draft note,
                -- its parent may already be invisible to this query.
                IF FOUND
                   AND (
                       note_status IN ('issued', 'cancelled')
                       OR note_immutable_at IS NOT NULL
                   )
                THEN
                    RAISE EXCEPTION
                        'Credit note % is not editable.',
                        target_credit_note_id
                        USING ERRCODE = '55006';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_protect_credit_note_item_content_trigger
            BEFORE INSERT OR UPDATE OR DELETE ON credit_note_items
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_credit_note_item_content()
            SQL);

    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_protect_original_payment_with_refunds_trigger ON payments'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_original_payment_with_refunds()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_protect_immutable_invoice_content_trigger ON invoices'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_immutable_invoice_content()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_invoice_lifecycle_trigger ON invoices'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_invoice_lifecycle()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_protect_invoice_total_after_activity_trigger ON invoices'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_invoice_total_after_activity()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_protect_credit_note_item_content_trigger ON credit_note_items'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_credit_note_item_content()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_z_protect_credit_note_content_trigger ON credit_notes'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_credit_note_content()'
        );

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
            'DROP TRIGGER IF EXISTS terra_protect_paid_invoice_allocation_trigger ON payment_allocations'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_paid_invoice_allocation()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_payment_allocation_trigger ON payment_allocations'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_payment_allocation()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_protect_active_refund_lifecycle_trigger ON payments'
        );
        DB::statement(
            'DROP FUNCTION IF EXISTS terra_protect_active_refund_lifecycle()'
        );

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

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['reservation_id']);
        });
    }
};
