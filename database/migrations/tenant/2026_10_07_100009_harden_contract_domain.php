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
        /*
         * Les invoices sont de l'historique financier.
         * Un contrat référencé par une facture ne doit jamais être
         * supprimé en laissant une facture orpheline.
         */
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['contract_id']);

            $table->foreign('contract_id')
                ->references('id')
                ->on('contracts')
                ->restrictOnDelete();
        });

        /*
         * Les amendements constituent également un historique contractuel.
         * Une suppression de contrat ne doit donc pas cascader cet historique.
         */
        Schema::table('contract_amendments', function (Blueprint $table): void {
            $table->dropForeign(['contract_id']);

            $table->foreign('contract_id')
                ->references('id')
                ->on('contracts')
                ->restrictOnDelete();
        });

        /*
         * Cohérence métier minimale des amendements.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_amount_type_check
            CHECK (
                type <> 'amount'
                OR new_amount IS NOT NULL
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_duration_type_check
            CHECK (
                type <> 'duration'
                OR new_end_date IS NOT NULL
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_approved_metadata_check
            CHECK (
                status <> 'approved'
                OR (
                    approved_by IS NOT NULL
                    AND approved_at IS NOT NULL
                    AND effective_date IS NOT NULL
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE contract_amendments
            ADD CONSTRAINT contract_amendments_effective_end_date_check
            CHECK (
                effective_date IS NULL
                OR new_end_date IS NULL
                OR new_end_date >= effective_date
            )
            SQL);

        /*
         * Amendments :
         *
         * - le contrat parent doit exister ;
         * - impossible de créer/rattacher un amendement à un contrat
         *   terminé, résilié ou expiré ;
         * - un amendement approuvé est immutable ;
         * - un amendement approuvé ne peut pas être supprimé.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_contract_amendment()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                contract_status VARCHAR(30);
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status = 'approved' THEN
                        RAISE EXCEPTION
                            'Approved contract amendment % is immutable and cannot be deleted.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;

                    RETURN OLD;
                END IF;

                SELECT status
                INTO contract_status
                FROM contracts
                WHERE id = NEW.contract_id
                FOR SHARE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Contract % does not exist.',
                        NEW.contract_id
                        USING ERRCODE = '23503';
                END IF;

                IF (
                    TG_OP = 'INSERT'
                    OR (
                        TG_OP = 'UPDATE'
                        AND NEW.contract_id IS DISTINCT FROM OLD.contract_id
                    )
                )
                AND contract_status IN (
                    'completed',
                    'terminated',
                    'expired'
                )
                THEN
                    RAISE EXCEPTION
                        'A contract amendment cannot be created or reassigned on a terminal contract.'
                        USING ERRCODE = '23514';
                END IF;

                IF TG_OP = 'UPDATE'
                   AND OLD.status = 'approved'
                   AND (
                       NEW.contract_id IS DISTINCT FROM OLD.contract_id
                       OR NEW.reference IS DISTINCT FROM OLD.reference
                       OR NEW.type IS DISTINCT FROM OLD.type
                       OR NEW.status IS DISTINCT FROM OLD.status
                       OR NEW.description IS DISTINCT FROM OLD.description
                       OR NEW.amount_delta IS DISTINCT FROM OLD.amount_delta
                       OR NEW.new_amount IS DISTINCT FROM OLD.new_amount
                       OR NEW.new_end_date IS DISTINCT FROM OLD.new_end_date
                       OR NEW.effective_date IS DISTINCT FROM OLD.effective_date
                       OR NEW.approved_by IS DISTINCT FROM OLD.approved_by
                       OR NEW.approved_at IS DISTINCT FROM OLD.approved_at
                   )
                THEN
                    RAISE EXCEPTION
                        'Approved contract amendment % is immutable.',
                        OLD.id
                        USING ERRCODE = '55006';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_contract_amendment_trigger
            BEFORE INSERT OR UPDATE OR DELETE
            ON contract_amendments
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_contract_amendment()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_contract_amendment_trigger ON contract_amendments'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_contract_amendment()'
        );

        DB::statement(
            'ALTER TABLE contract_amendments
             DROP CONSTRAINT IF EXISTS contract_amendments_effective_end_date_check'
        );

        DB::statement(
            'ALTER TABLE contract_amendments
             DROP CONSTRAINT IF EXISTS contract_amendments_approved_metadata_check'
        );

        DB::statement(
            'ALTER TABLE contract_amendments
             DROP CONSTRAINT IF EXISTS contract_amendments_duration_type_check'
        );

        DB::statement(
            'ALTER TABLE contract_amendments
             DROP CONSTRAINT IF EXISTS contract_amendments_amount_type_check'
        );

        Schema::table('contract_amendments', function (Blueprint $table): void {
            $table->dropForeign(['contract_id']);

            $table->foreign('contract_id')
                ->references('id')
                ->on('contracts')
                ->cascadeOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['contract_id']);

            $table->foreign('contract_id')
                ->references('id')
                ->on('contracts')
                ->nullOnDelete();
        });
    }
};
