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
        DB::statement(
            'DROP INDEX IF EXISTS reservations_active_offer_unique'
        );

        DB::statement(<<<'SQL'
            ALTER TABLE reservations
            DROP CONSTRAINT IF EXISTS reservations_amount_check
            SQL);

        DB::statement(
            'ALTER TABLE reservations RENAME COLUMN amount TO deposit_amount'
        );

        Schema::table('reservations', function (Blueprint $table): void {
            $table->decimal('agreed_price', 20, 4)->nullable();
            $table->date('deposit_due_date')->nullable();

            $table->index('deposit_due_date');
        });

        DB::statement(<<<'SQL'
            UPDATE reservations AS reservations
            SET agreed_price = commercial_offers.price
            FROM commercial_offers
            WHERE commercial_offers.id = reservations.commercial_offer_id
              AND reservations.agreed_price IS NULL
            SQL);

        DB::statement(
            'ALTER TABLE reservations ALTER COLUMN agreed_price SET NOT NULL'
        );

        DB::statement(<<<'SQL'
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_agreed_price_check
            CHECK (agreed_price >= 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_deposit_amount_check
            CHECK (
                deposit_amount >= 0
                AND deposit_amount <= agreed_price
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_prevent_confirmed_reservation_conflicts()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                target RECORD;
            BEGIN
                IF NEW.status <> 'confirmed' THEN
                    RETURN NEW;
                END IF;

                /*
                 * Serialize confirmation attempts on every physical target
                 * contained in the commercial offer.
                 *
                 * Sorting targets before taking advisory locks avoids
                 * deadlocks when a bundle contains multiple targets.
                 */
                FOR target IN
                    SELECT kind, target_id
                    FROM (
                        SELECT
                            'land' AS kind,
                            land_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND land_id IS NOT NULL

                        UNION ALL

                        SELECT
                            'parcel' AS kind,
                            parcel_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND parcel_id IS NOT NULL

                        UNION ALL

                        SELECT
                            'property' AS kind,
                            property_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND property_id IS NOT NULL

                        UNION ALL

                        SELECT
                            'unit' AS kind,
                            unit_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND unit_id IS NOT NULL
                    ) AS targets
                    ORDER BY kind, target_id
                LOOP
                    PERFORM pg_advisory_xact_lock(
                        hashtextextended(
                            target.kind || ':' || target.target_id,
                            0
                        )
                    );
                END LOOP;

                /*
                 * A confirmed reservation cannot overlap a target already
                 * held by another confirmed reservation, even through
                 * different commercial offers.
                 */
                IF EXISTS (
                    SELECT 1
                    FROM reservations AS existing_reservation
                    JOIN commercial_offer_items AS existing_item
                        ON existing_item.commercial_offer_id =
                           existing_reservation.commercial_offer_id
                    JOIN commercial_offer_items AS new_item
                        ON new_item.commercial_offer_id =
                           NEW.commercial_offer_id
                    WHERE existing_reservation.status = 'confirmed'
                      AND existing_reservation.id <> NEW.id
                      AND (
                           (
                               new_item.land_id IS NOT NULL
                               AND existing_item.land_id = new_item.land_id
                           )
                           OR
                           (
                               new_item.parcel_id IS NOT NULL
                               AND existing_item.parcel_id = new_item.parcel_id
                           )
                           OR
                           (
                               new_item.property_id IS NOT NULL
                               AND existing_item.property_id = new_item.property_id
                           )
                           OR
                           (
                               new_item.unit_id IS NOT NULL
                               AND existing_item.unit_id = new_item.unit_id
                           )
                      )
                ) THEN
                    RAISE EXCEPTION
                        'A confirmed reservation already holds a target of commercial offer %.',
                        NEW.commercial_offer_id
                        USING ERRCODE = '23505';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_confirmed_reservation_conflict_trigger
            BEFORE INSERT OR UPDATE OF status, commercial_offer_id
            ON reservations
            FOR EACH ROW
            EXECUTE FUNCTION terra_prevent_confirmed_reservation_conflicts()
            SQL);

        /*
         * Once an offer has a confirmed reservation, its composition becomes
         * immutable. This keeps the confirmed reservation tied to the same
         * commercial bundle that was checked above.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_freeze_confirmed_offer_items()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP IN ('INSERT', 'UPDATE')
                   AND EXISTS (
                       SELECT 1
                       FROM reservations
                       WHERE commercial_offer_id = NEW.commercial_offer_id
                         AND status = 'confirmed'
                   )
                THEN
                    RAISE EXCEPTION
                        'Commercial offer % has a confirmed reservation and its items are frozen.',
                        NEW.commercial_offer_id
                        USING ERRCODE = '55006';
                END IF;

                IF TG_OP IN ('UPDATE', 'DELETE')
                   AND EXISTS (
                       SELECT 1
                       FROM reservations
                       WHERE commercial_offer_id = OLD.commercial_offer_id
                         AND status = 'confirmed'
                   )
                THEN
                    RAISE EXCEPTION
                        'Commercial offer % has a confirmed reservation and its items are frozen.',
                        OLD.commercial_offer_id
                        USING ERRCODE = '55006';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_freeze_confirmed_offer_items_trigger
            BEFORE INSERT OR UPDATE OR DELETE
            ON commercial_offer_items
            FOR EACH ROW
            EXECUTE FUNCTION terra_freeze_confirmed_offer_items()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_freeze_confirmed_offer_items_trigger ON commercial_offer_items'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_freeze_confirmed_offer_items()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_confirmed_reservation_conflict_trigger ON reservations'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_prevent_confirmed_reservation_conflicts()'
        );

        DB::statement(
            'ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_deposit_amount_check'
        );

        DB::statement(
            'ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_agreed_price_check'
        );

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['deposit_due_date']);
            $table->dropColumn([
                'agreed_price',
                'deposit_due_date',
            ]);
        });

        DB::statement(
            'ALTER TABLE reservations RENAME COLUMN deposit_amount TO amount'
        );

        DB::statement(<<<'SQL'
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_amount_check
            CHECK (amount >= 0)
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX reservations_active_offer_unique
            ON reservations (commercial_offer_id)
            WHERE status IN ('pending', 'confirmed')
            SQL);
    }
};
