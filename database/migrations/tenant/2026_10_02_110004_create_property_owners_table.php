<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_owners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('property_id');
            $table->ulid('party_id');

            $table->decimal('ownership_percentage', 5, 2);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestampsTz();

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->cascadeOnDelete();

            $table->foreign('party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            /*
             * Historical ownership is allowed:
             * the same party may own the same property during
             * multiple distinct ownership periods.
             */
            $table->index(['property_id', 'party_id']);
            $table->index('party_id');
            $table->index([
                'property_id',
                'start_date',
                'end_date',
            ]);
        });

        /*
         * Ownership percentage must be strictly positive
         * and cannot exceed 100%.
         */
        DB::statement(
            'ALTER TABLE property_owners
             ADD CONSTRAINT property_owners_percentage_check
             CHECK (
                 ownership_percentage > 0
                 AND ownership_percentage <= 100
             )'
        );

        /*
         * End date cannot precede start date.
         */
        DB::statement(
            'ALTER TABLE property_owners
             ADD CONSTRAINT property_owners_dates_check
             CHECK (
                 start_date IS NULL
                 OR end_date IS NULL
                 OR end_date >= start_date
             )'
        );

        /*
         * At any given moment, the total active ownership
         * of a property cannot exceed 100%.
         *
         * NULL start_date = ownership starts immediately.
         * NULL end_date   = ownership has no end date.
         *
         * The advisory transaction lock prevents concurrent
         * transactions from independently exceeding 100%.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION validate_property_ownership_total()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $func$
            DECLARE
                total numeric;
            BEGIN
                PERFORM pg_advisory_xact_lock(
                    hashtextextended(NEW.property_id::text, 0)
                );

                SELECT COALESCE(SUM(ownership_percentage), 0)
                INTO total
                FROM property_owners
                WHERE property_id = NEW.property_id
                  AND (start_date IS NULL OR start_date <= CURRENT_DATE)
                  AND (end_date IS NULL OR end_date >= CURRENT_DATE)
                  AND id <> NEW.id;

                IF total + NEW.ownership_percentage > 100 THEN
                    RAISE EXCEPTION
                        'Active ownership percentage for property % cannot exceed 100%%',
                        NEW.property_id;
                END IF;

                RETURN NEW;
            END;
            $func$;
            SQL
        );

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_owners_validate_total
            BEFORE INSERT OR UPDATE OF
                property_id,
                ownership_percentage,
                start_date,
                end_date
            ON property_owners
            FOR EACH ROW
            EXECUTE FUNCTION validate_property_ownership_total();
            SQL
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS property_owners_validate_total
             ON property_owners'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS validate_property_ownership_total()'
        );

        Schema::dropIfExists('property_owners');
    }
};