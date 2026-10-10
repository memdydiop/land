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
                overlapping_same_party boolean;
                max_total numeric;
            BEGIN
                PERFORM pg_advisory_xact_lock(
                    hashtextextended(NEW.property_id::text, 0)
                );

                /*
                 * Same party / same property:
                 * periods must not overlap.
                 */
                SELECT EXISTS (
                    SELECT 1
                    FROM property_owners po
                    WHERE po.property_id = NEW.property_id
                      AND po.party_id = NEW.party_id
                      AND po.id <> NEW.id
                      AND COALESCE(po.start_date, '-infinity'::date)
                            <= COALESCE(NEW.end_date, 'infinity'::date)
                      AND COALESCE(po.end_date, 'infinity'::date)
                            >= COALESCE(NEW.start_date, '-infinity'::date)
                )
                INTO overlapping_same_party;

                IF overlapping_same_party THEN
                    RAISE EXCEPTION
                        'Party % already has an overlapping ownership period for property %.',
                        NEW.party_id,
                        NEW.property_id
                        USING ERRCODE = '23514';
                END IF;

                /*
                 * Build a temporal sweep:
                 *
                 * Every ownership interval generates:
                 *   +percentage at start
                 *   -percentage just after end
                 *
                 * Aggregating by date first is important so several
                 * starts/ends on the same boundary are handled atomically.
                 */
                WITH relevant AS (
                    SELECT
                        COALESCE(NEW.start_date, '-infinity'::date) AS start_date,
                        COALESCE(NEW.end_date, 'infinity'::date) AS end_date,
                        NEW.ownership_percentage AS ownership_percentage

                    UNION ALL

                    SELECT
                        COALESCE(po.start_date, '-infinity'::date),
                        COALESCE(po.end_date, 'infinity'::date),
                        po.ownership_percentage
                    FROM property_owners po
                    WHERE po.property_id = NEW.property_id
                      AND po.id <> NEW.id
                      AND COALESCE(po.start_date, '-infinity'::date)
                            <= COALESCE(NEW.end_date, 'infinity'::date)
                      AND COALESCE(po.end_date, 'infinity'::date)
                            >= COALESCE(NEW.start_date, '-infinity'::date)
                ),
                events AS (
                    SELECT
                        start_date AS event_date,
                        SUM(ownership_percentage) AS delta
                    FROM relevant
                    GROUP BY start_date

                    UNION ALL

                    SELECT
                        end_date + 1 AS event_date,
                        -SUM(ownership_percentage) AS delta
                    FROM relevant
                    GROUP BY end_date
                ),
                aggregated_events AS (
                    SELECT
                        event_date,
                        SUM(delta) AS delta
                    FROM events
                    GROUP BY event_date
                ),
                sweep AS (
                    SELECT
                        event_date,
                        SUM(delta) OVER (
                            ORDER BY event_date
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS running_total
                    FROM aggregated_events
                )
                SELECT COALESCE(MAX(running_total), 0)
                INTO max_total
                FROM sweep;

                IF max_total > 100 THEN
                    RAISE EXCEPTION
                        'Ownership percentage for property % cannot exceed 100%% at any point in time. Maximum detected: %%%.',
                        NEW.property_id,
                        max_total
                        USING ERRCODE = '23514';
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
                party_id,
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