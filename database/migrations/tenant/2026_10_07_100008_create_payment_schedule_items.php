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
        Schema::create('payment_schedule_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('sale_id');
            $table->unsignedSmallInteger('position');
            $table->string('label', 160);
            $table->string('trigger_type', 20);
            $table->string('trigger_reference', 160)->nullable();
            $table->date('due_on')->nullable();
            $table->decimal('amount', 20, 4);
            $table->decimal('percentage', 7, 4)->nullable();
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->unique(['sale_id', 'position']);

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->restrictOnDelete();

            $table->index(['sale_id', 'due_on']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payment_schedule_items
            ADD CONSTRAINT payment_schedule_items_position_check
            CHECK (position > 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payment_schedule_items
            ADD CONSTRAINT payment_schedule_items_amount_check
            CHECK (amount > 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payment_schedule_items
            ADD CONSTRAINT payment_schedule_items_percentage_check
            CHECK (
                percentage IS NULL
                OR (percentage > 0 AND percentage <= 100)
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payment_schedule_items
            ADD CONSTRAINT payment_schedule_items_trigger_check
            CHECK (
                (
                    trigger_type = 'date'
                    AND due_on IS NOT NULL
                    AND trigger_reference IS NULL
                )
                OR
                (
                    trigger_type = 'milestone'
                    AND trigger_reference IS NOT NULL
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payment_schedule_items
            ADD CONSTRAINT payment_schedule_items_trigger_type_check
            CHECK (trigger_type IN ('date', 'milestone'))
            SQL);

        /*
         * L'échéancier appartient à la Sale.
         *
         * Règles :
         * - devise = devise de la Sale ;
         * - somme des montants <= prix de vente ;
         * - somme des pourcentages renseignés <= 100 % ;
         * - une échéance déjà facturée devient immuable ;
         * - le sale_id ne peut plus changer.
         *
         * Le verrou sur la Sale sérialise les modifications concurrentes
         * de l'échéancier d'une même vente.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_payment_schedule_item()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                sale_record RECORD;
                current_amount NUMERIC(20,4);
                current_percentage NUMERIC(20,4);
                schedule_id TEXT;
                new_sale_id TEXT;
            BEGIN
                schedule_id := CASE
                    WHEN TG_OP = 'DELETE' THEN OLD.id::text
                    ELSE NEW.id::text
                END;

                new_sale_id := CASE
                    WHEN TG_OP = 'DELETE' THEN OLD.sale_id::text
                    ELSE NEW.sale_id::text
                END;

                IF TG_OP = 'UPDATE'
                   AND NEW.sale_id IS DISTINCT FROM OLD.sale_id
                THEN
                    RAISE EXCEPTION
                        'A payment schedule item cannot move to another sale.'
                        USING ERRCODE = '55006';
                END IF;

                IF TG_OP IN ('UPDATE', 'DELETE')
                   AND EXISTS (
                       SELECT 1
                       FROM invoices
                       WHERE schedule_item_id = OLD.id
                   )
                THEN
                    RAISE EXCEPTION
                        'Payment schedule item % has invoices and is frozen.',
                        OLD.id
                        USING ERRCODE = '55006';
                END IF;

                SELECT id, agreed_price, currency
                INTO sale_record
                FROM sales
                WHERE id = new_sale_id::text
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Sale % does not exist.',
                        new_sale_id
                        USING ERRCODE = '23503';
                END IF;

                IF TG_OP <> 'DELETE'
                   AND NEW.currency <> sale_record.currency
                THEN
                    RAISE EXCEPTION
                        'Payment schedule currency must match sale currency.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT
                    COALESCE(SUM(amount), 0),
                    COALESCE(SUM(percentage), 0)
                INTO
                    current_amount,
                    current_percentage
                FROM payment_schedule_items
                WHERE sale_id = new_sale_id::text
                  AND id <> schedule_id::text;

                IF TG_OP <> 'DELETE'
                   AND current_amount + NEW.amount > sale_record.agreed_price
                THEN
                    RAISE EXCEPTION
                        'Payment schedule amounts exceed sale agreed price.'
                        USING ERRCODE = '23514';
                END IF;

                IF TG_OP <> 'DELETE'
                   AND current_percentage + COALESCE(NEW.percentage, 0) > 100
                THEN
                    RAISE EXCEPTION
                        'Payment schedule percentages exceed 100 percent.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_payment_schedule_item_trigger
            BEFORE INSERT OR UPDATE OR DELETE
            ON payment_schedule_items
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_payment_schedule_item()
            SQL);

        /*
         * Une facture issue d'un échéancier doit appartenir au même
         * contrat / Sale que l'échéance.
         *
         * Le total facturé sur une échéance ne peut pas dépasser
         * son montant prévu.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_invoice_schedule_item()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                schedule_record RECORD;
                contract_record RECORD;
                invoiced_amount NUMERIC(20,4);
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND EXISTS (
                       SELECT 1
                       FROM payment_allocations
                       WHERE invoice_id = NEW.id
                   )
                   AND NEW.schedule_item_id IS DISTINCT FROM OLD.schedule_item_id
                THEN
                    RAISE EXCEPTION
                        'Invoice % has payment allocations and its schedule item is frozen.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                IF NEW.schedule_item_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT
                    id,
                    sale_id,
                    amount,
                    currency
                INTO schedule_record
                FROM payment_schedule_items
                WHERE id = NEW.schedule_item_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Payment schedule item % does not exist.',
                        NEW.schedule_item_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.contract_id IS NULL THEN
                    RAISE EXCEPTION
                        'An invoice linked to a payment schedule item must reference a contract.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT
                    id,
                    sale_id,
                    party_id,
                    currency
                INTO contract_record
                FROM contracts
                WHERE id = NEW.contract_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Contract % does not exist.',
                        NEW.contract_id
                        USING ERRCODE = '23503';
                END IF;

                IF contract_record.sale_id IS DISTINCT FROM schedule_record.sale_id
                THEN
                    RAISE EXCEPTION
                        'Invoice contract must belong to the same sale as its payment schedule item.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.client_party_id <> contract_record.party_id
                THEN
                    RAISE EXCEPTION
                        'Invoice client must match contract party.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.currency <> contract_record.currency
                   OR NEW.currency <> schedule_record.currency
                THEN
                    RAISE EXCEPTION
                        'Invoice currency must match contract and schedule currencies.'
                        USING ERRCODE = '23514';
                END IF;

                SELECT COALESCE(SUM(total), 0)
                INTO invoiced_amount
                FROM invoices
                WHERE schedule_item_id = NEW.schedule_item_id
                  AND id <> NEW.id
                  AND status <> 'cancelled';

                IF invoiced_amount + NEW.total > schedule_record.amount
                THEN
                    RAISE EXCEPTION
                        'Invoice totals exceed payment schedule item amount.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->ulid('schedule_item_id')
                ->nullable()
                ->after('work_situation_id');

            $table->foreign('schedule_item_id')
                ->references('id')
                ->on('payment_schedule_items')
                ->restrictOnDelete();

            $table->index('schedule_item_id');
        });

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_invoice_schedule_item_trigger
            BEFORE INSERT OR UPDATE OF
                schedule_item_id,
                contract_id,
                client_party_id,
                currency,
                total
            ON invoices
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_invoice_schedule_item()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_invoice_schedule_item_trigger ON invoices'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_invoice_schedule_item()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_payment_schedule_item_trigger ON payment_schedule_items'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_payment_schedule_item()'
        );

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['schedule_item_id']);
            $table->dropIndex(['schedule_item_id']);
            $table->dropColumn('schedule_item_id');
        });

        Schema::dropIfExists('payment_schedule_items');
    }
};
