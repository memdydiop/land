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
        Schema::create('sales', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('reservation_id');
            $table->ulid('buyer_party_id');

            $table->string('reference');
            $table->string('type');
            $table->string('status');

            $table->decimal('agreed_price', 20, 4);
            $table->char('currency', 3);
            $table->timestampTz('sold_at')->nullable();

            $table->timestampsTz();

            $table->unique('reservation_id');
            $table->unique('reference');

            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->restrictOnDelete();

            $table->foreign('buyer_party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->index('buyer_party_id');
            $table->index('status');
            $table->index('type');
            $table->index('sold_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE sales
            ADD CONSTRAINT sales_type_check
            CHECK (type IN ('land', 'real_estate', 'mixed', 'other'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE sales
            ADD CONSTRAINT sales_status_check
            CHECK (status IN ('draft', 'confirmed', 'completed', 'cancelled'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE sales
            ADD CONSTRAINT sales_agreed_price_check
            CHECK (agreed_price >= 0)
            SQL);

        Schema::table('contracts', function (Blueprint $table): void {
            $table->ulid('sale_id')->nullable()->after('party_id');

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->restrictOnDelete();

            $table->index(['sale_id', 'status']);
        });

        /*
         * A reservation that has been converted into a Sale continues
         * to hold its commercial targets. This prevents the same Parcel,
         * Property or Unit from becoming reservable again.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_prevent_reservation_after_conversion()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                target RECORD;
            BEGIN
                IF NEW.status <> 'confirmed' THEN
                    RETURN NEW;
                END IF;

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

                IF EXISTS (
                    SELECT 1
                    FROM reservations AS existing_reservation
                    JOIN commercial_offer_items AS existing_item
                        ON existing_item.commercial_offer_id =
                           existing_reservation.commercial_offer_id
                    JOIN commercial_offer_items AS new_item
                        ON new_item.commercial_offer_id =
                           NEW.commercial_offer_id
                    WHERE existing_reservation.id <> NEW.id
                      AND existing_reservation.status = 'converted'
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
                        'A converted reservation already holds a target of commercial offer %.',
                        NEW.commercial_offer_id
                        USING ERRCODE = '23505';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_prevent_reservation_after_conversion_trigger
            BEFORE INSERT OR UPDATE OF status, commercial_offer_id
            ON reservations
            FOR EACH ROW
            EXECUTE FUNCTION terra_prevent_reservation_after_conversion()
            SQL);

        /*
         * Commercial offer composition remains immutable after conversion.
         * The original 5B trigger freezes confirmed reservations; this
         * companion trigger extends the freeze to converted reservations.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_freeze_converted_offer_items()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP IN ('INSERT', 'UPDATE')
                   AND EXISTS (
                       SELECT 1
                       FROM reservations
                       WHERE commercial_offer_id = NEW.commercial_offer_id
                         AND status = 'converted'
                   )
                THEN
                    RAISE EXCEPTION
                        'Commercial offer % has a converted reservation and its items are frozen.',
                        NEW.commercial_offer_id
                        USING ERRCODE = '55006';
                END IF;

                IF TG_OP IN ('UPDATE', 'DELETE')
                   AND EXISTS (
                       SELECT 1
                       FROM reservations
                       WHERE commercial_offer_id = OLD.commercial_offer_id
                         AND status = 'converted'
                   )
                THEN
                    RAISE EXCEPTION
                        'Commercial offer % has a converted reservation and its items are frozen.',
                        OLD.commercial_offer_id
                        USING ERRCODE = '55006';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_freeze_converted_offer_items_trigger
            BEFORE INSERT OR UPDATE OR DELETE
            ON commercial_offer_items
            FOR EACH ROW
            EXECUTE FUNCTION terra_freeze_converted_offer_items()
            SQL);

        /*
         * A Sale must originate from a confirmed/converted reservation,
         * retain the same buyer and currency, and serialize confirmation
         * attempts on all physical targets.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_sale()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                reservation_record RECORD;
                target RECORD;
            BEGIN
                SELECT
                    id,
                    party_id,
                    commercial_offer_id,
                    status,
                    currency
                INTO reservation_record
                FROM reservations
                WHERE id = NEW.reservation_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Reservation % does not exist.',
                        NEW.reservation_id
                        USING ERRCODE = '23503';
                END IF;

                IF reservation_record.status NOT IN ('confirmed', 'converted') THEN
                    RAISE EXCEPTION
                        'Reservation % is not confirmed or converted.',
                        NEW.reservation_id
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.buyer_party_id <> reservation_record.party_id THEN
                    RAISE EXCEPTION
                        'Sale buyer must match reservation party.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.currency <> reservation_record.currency THEN
                    RAISE EXCEPTION
                        'Sale currency must match reservation currency.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status NOT IN ('confirmed', 'completed') THEN
                    RETURN NEW;
                END IF;

                FOR target IN
                    SELECT kind, target_id
                    FROM (
                        SELECT
                            'land' AS kind,
                            land_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id =
                              reservation_record.commercial_offer_id
                          AND land_id IS NOT NULL

                        UNION ALL

                        SELECT
                            'parcel' AS kind,
                            parcel_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id =
                              reservation_record.commercial_offer_id
                          AND parcel_id IS NOT NULL

                        UNION ALL

                        SELECT
                            'property' AS kind,
                            property_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id =
                              reservation_record.commercial_offer_id
                          AND property_id IS NOT NULL

                        UNION ALL

                        SELECT
                            'unit' AS kind,
                            unit_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id =
                              reservation_record.commercial_offer_id
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

                IF EXISTS (
                    SELECT 1
                    FROM sales AS existing_sale
                    JOIN reservations AS existing_reservation
                        ON existing_reservation.id =
                           existing_sale.reservation_id
                    JOIN commercial_offer_items AS existing_item
                        ON existing_item.commercial_offer_id =
                           existing_reservation.commercial_offer_id
                    JOIN commercial_offer_items AS new_item
                        ON new_item.commercial_offer_id =
                           reservation_record.commercial_offer_id
                    WHERE existing_sale.id <> NEW.id
                      AND existing_sale.status IN ('confirmed', 'completed')
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
                        'A confirmed sale already holds a target of reservation %.',
                        NEW.reservation_id
                        USING ERRCODE = '23505';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_sale_trigger
            BEFORE INSERT OR UPDATE OF reservation_id, buyer_party_id, currency, status
            ON sales
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_sale()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_sale_trigger ON sales'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_sale()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_freeze_converted_offer_items_trigger ON commercial_offer_items'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_freeze_converted_offer_items()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_prevent_reservation_after_conversion_trigger ON reservations'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_prevent_reservation_after_conversion()'
        );

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropIndex(['sale_id', 'status']);
            $table->dropColumn('sale_id');
        });

        Schema::dropIfExists('sales');
    }
};
