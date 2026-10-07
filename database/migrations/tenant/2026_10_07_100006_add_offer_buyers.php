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
        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->ulid('buyer_party_id')->nullable()->after('description');

            $table->foreign('buyer_party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->index('buyer_party_id');
        });

        Schema::create('offer_co_buyers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('commercial_offer_id');
            $table->ulid('party_id');
            $table->decimal('share', 5, 2)->nullable();
            $table->timestampsTz();

            $table->unique(['commercial_offer_id', 'party_id']);

            $table->foreign('commercial_offer_id')
                ->references('id')
                ->on('commercial_offers')
                ->cascadeOnDelete();

            $table->foreign('party_id')
                ->references('id')
                ->on('parties')
                ->restrictOnDelete();

            $table->index('party_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE offer_co_buyers
            ADD CONSTRAINT offer_co_buyers_share_check
            CHECK (
                share IS NULL
                OR (share > 0 AND share <= 100)
            )
            SQL);

        /*
         * A primary buyer cannot also be a co-buyer.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_offer_buyer()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF NEW.buyer_party_id IS NOT NULL
                   AND EXISTS (
                       SELECT 1
                       FROM reservations
                       WHERE commercial_offer_id = NEW.id
                         AND status IN ('pending', 'confirmed', 'converted')
                         AND party_id <> NEW.buyer_party_id
                   )
                THEN
                    RAISE EXCEPTION
                        'Commercial offer % has an active reservation for another buyer.',
                        NEW.id
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.buyer_party_id IS NOT NULL
                   AND EXISTS (
                       SELECT 1
                       FROM offer_co_buyers
                       WHERE commercial_offer_id = NEW.id
                         AND party_id = NEW.buyer_party_id
                   )
                THEN
                    RAISE EXCEPTION
                        'Party % cannot be both primary buyer and co-buyer on offer %.',
                        NEW.buyer_party_id,
                        NEW.id
                        USING ERRCODE = '23514';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM reservations
                    WHERE commercial_offer_id = NEW.id
                      AND status IN ('confirmed', 'converted')
                ) THEN
                    RAISE EXCEPTION
                        'Commercial offer % has a confirmed or converted reservation and its buyer is frozen.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_offer_buyer_trigger
            BEFORE UPDATE OF buyer_party_id
            ON commercial_offers
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_offer_buyer()
            SQL);

        /*
         * Co-buyers remain editable until the reservation is confirmed
         * or converted.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_offer_co_buyer()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                offer_id text;
                primary_buyer text;
            BEGIN
                offer_id := COALESCE(
                    NEW.commercial_offer_id,
                    OLD.commercial_offer_id
                );

                SELECT buyer_party_id
                INTO primary_buyer
                FROM commercial_offers
                WHERE id = offer_id;

                IF TG_OP IN ('INSERT', 'UPDATE')
                   AND NEW.party_id = primary_buyer
                THEN
                    RAISE EXCEPTION
                        'Party % is already the primary buyer of offer %.',
                        NEW.party_id,
                        offer_id
                        USING ERRCODE = '23514';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM reservations
                    WHERE commercial_offer_id = offer_id
                      AND status IN ('confirmed', 'converted')
                ) THEN
                    RAISE EXCEPTION
                        'Commercial offer % has a confirmed or converted reservation and its co-buyers are frozen.',
                        offer_id
                        USING ERRCODE = '55006';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_offer_co_buyer_trigger
            BEFORE INSERT OR UPDATE OR DELETE
            ON offer_co_buyers
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_offer_co_buyer()
            SQL);

        /*
         * Once the offer has a primary buyer, the reservation principal
         * must be that same party.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_reservation_buyer()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                primary_buyer text;
            BEGIN
                SELECT buyer_party_id
                INTO primary_buyer
                FROM commercial_offers
                WHERE id = NEW.commercial_offer_id;

                IF primary_buyer IS NOT NULL
                   AND NEW.party_id <> primary_buyer
                THEN
                    RAISE EXCEPTION
                        'Reservation party % must match primary buyer % of offer %.',
                        NEW.party_id,
                        primary_buyer,
                        NEW.commercial_offer_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_reservation_buyer_trigger
            BEFORE INSERT OR UPDATE OF party_id, commercial_offer_id
            ON reservations
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_reservation_buyer()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_reservation_buyer_trigger ON reservations'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_reservation_buyer()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_offer_co_buyer_trigger ON offer_co_buyers'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_offer_co_buyer()'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_offer_buyer_trigger ON commercial_offers'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_validate_offer_buyer()'
        );

        Schema::dropIfExists('offer_co_buyers');

        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->dropForeign(['buyer_party_id']);
            $table->dropIndex(['buyer_party_id']);
            $table->dropColumn('buyer_party_id');
        });
    }
};
