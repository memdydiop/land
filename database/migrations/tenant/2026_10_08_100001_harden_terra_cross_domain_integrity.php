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
        // -----------------------------------------------------------------
        // Parcel identity: keep the V1 addition deliberately minimal.
        // -----------------------------------------------------------------
        Schema::table('parcels', function (Blueprint $table): void {
            $table->string('lot_number')->nullable()->after('parcel_number');
            $table->string('cadastral_reference')->nullable()->after('lot_number');
        });

        $this->addSubdivisionOperationIntegrity();
        $this->addCommercialOfferIntegrity();
        $this->addCommercialExclusivityGuards();
        $this->addFinancialAllocationGuards();
    }

    private function addSubdivisionOperationIntegrity(): void
    {
        Schema::table('subdivision_lands', function (Blueprint $table): void {
            $table->ulid('operation_id')->nullable()->after('subdivision_id');
        });

        DB::statement(<<<'SQL'
            UPDATE subdivision_lands AS sl
            SET operation_id = s.operation_id
            FROM subdivisions AS s
            WHERE s.id = sl.subdivision_id
              AND sl.operation_id IS NULL
            SQL);

        DB::statement(
            'ALTER TABLE subdivision_lands ALTER COLUMN operation_id SET NOT NULL'
        );

        DB::statement(<<<'SQL'
            ALTER TABLE subdivisions
            ADD CONSTRAINT subdivisions_operation_id_id_unique
            UNIQUE (operation_id, id)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subdivision_lands
            ADD CONSTRAINT subdivision_lands_operation_subdivision_fk
            FOREIGN KEY (operation_id, subdivision_id)
            REFERENCES subdivisions (operation_id, id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subdivision_lands
            ADD CONSTRAINT subdivision_lands_operation_land_fk
            FOREIGN KEY (operation_id, land_id)
            REFERENCES operation_lands (operation_id, land_id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT
            SQL);
    }

    private function addCommercialOfferIntegrity(): void
    {
        // A committed offer may have one Reservation only. Multiple pending
        // reservations remain allowed until one of them is confirmed.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX reservations_committed_offer_unique
            ON reservations (commercial_offer_id)
            WHERE status IN ('confirmed', 'converted')
            SQL);

        // Reservation must stay on the offer's currency and cannot become
        // meaningful (confirmed/converted) without at least one item.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_validate_reservation_offer_integrity()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                offer_currency CHAR(3);
                offer_status TEXT;
                item_count INTEGER;
            BEGIN
                SELECT currency, status
                INTO offer_currency, offer_status
                FROM commercial_offers
                WHERE id = NEW.commercial_offer_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Commercial offer % does not exist.',
                        NEW.commercial_offer_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.currency <> offer_currency THEN
                    RAISE EXCEPTION
                        'Reservation currency must match commercial offer currency.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status = 'confirmed' AND offer_status <> 'active' THEN
                    RAISE EXCEPTION
                        'A reservation can only be confirmed against an active commercial offer.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status IN ('confirmed', 'converted') THEN
                    SELECT COUNT(*)
                    INTO item_count
                    FROM commercial_offer_items
                    WHERE commercial_offer_id = NEW.commercial_offer_id;

                    IF item_count = 0 THEN
                        RAISE EXCEPTION
                            'A reservation in status % requires at least one commercial offer item.',
                            NEW.status
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_validate_reservation_offer_integrity_trigger
            BEFORE INSERT OR UPDATE OF commercial_offer_id, currency, status
            ON reservations
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_reservation_offer_integrity()
            SQL);
    }

    private function addCommercialExclusivityGuards(): void
    {
        // Resolve a conservative, deterministic commercial conflict relation.
        // Land-level ancestry is used only when a subdivision has exactly one
        // underlying Land; the current schema cannot map a parcel to one Land
        // unambiguously when a subdivision spans multiple Lands.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_commercial_targets_conflict(
                kind_a TEXT,
                id_a TEXT,
                kind_b TEXT,
                id_b TEXT
            )
            RETURNS BOOLEAN
            LANGUAGE plpgsql
            STABLE
            AS $function$
            BEGIN
                IF kind_a = kind_b AND id_a = id_b THEN
                    RETURN TRUE;
                END IF;

                IF kind_a = 'property' AND kind_b = 'parcel' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM property_parcels pp
                        WHERE pp.property_id = id_a
                          AND pp.parcel_id = id_b
                    );
                END IF;

                IF kind_a = 'parcel' AND kind_b = 'property' THEN
                    RETURN terra_commercial_targets_conflict(
                        kind_b, id_b, kind_a, id_a
                    );
                END IF;

                IF kind_a = 'property' AND kind_b = 'unit' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM units u
                        JOIN buildings b ON b.id = u.building_id
                        WHERE u.id = id_b
                          AND b.property_id = id_a
                    );
                END IF;

                IF kind_a = 'unit' AND kind_b = 'property' THEN
                    RETURN terra_commercial_targets_conflict(
                        kind_b, id_b, kind_a, id_a
                    );
                END IF;

                IF kind_a = 'parcel' AND kind_b = 'unit' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM property_parcels pp
                        JOIN units u
                            ON u.id = id_b
                        JOIN buildings b
                            ON b.id = u.building_id
                        WHERE pp.parcel_id = id_a
                          AND pp.property_id = b.property_id
                    );
                END IF;

                IF kind_a = 'unit' AND kind_b = 'parcel' THEN
                    RETURN terra_commercial_targets_conflict(
                        kind_b, id_b, kind_a, id_a
                    );
                END IF;

                IF kind_a = 'property' AND kind_b = 'property' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM property_parcels pp1
                        JOIN property_parcels pp2
                            ON pp2.parcel_id = pp1.parcel_id
                        WHERE pp1.property_id = id_a
                          AND pp2.property_id = id_b
                    );
                END IF;

                IF kind_a = 'land' AND kind_b = 'parcel' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM parcels p
                        JOIN ilots i
                            ON i.id = p.ilot_id
                        JOIN subdivision_lands sl
                            ON sl.subdivision_id = i.subdivision_id
                        WHERE p.id = id_b
                          AND sl.land_id = id_a
                          AND NOT EXISTS (
                              SELECT 1
                              FROM subdivision_lands sl2
                              WHERE sl2.subdivision_id = sl.subdivision_id
                                AND sl2.land_id <> sl.land_id
                          )
                    );
                END IF;

                IF kind_a = 'parcel' AND kind_b = 'land' THEN
                    RETURN terra_commercial_targets_conflict(
                        kind_b, id_b, kind_a, id_a
                    );
                END IF;

                IF kind_a = 'land' AND kind_b = 'property' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM property_parcels pp
                        JOIN parcels p
                            ON p.id = pp.parcel_id
                        JOIN ilots i
                            ON i.id = p.ilot_id
                        JOIN subdivision_lands sl
                            ON sl.subdivision_id = i.subdivision_id
                        WHERE pp.property_id = id_b
                          AND sl.land_id = id_a
                          AND NOT EXISTS (
                              SELECT 1
                              FROM subdivision_lands sl2
                              WHERE sl2.subdivision_id = sl.subdivision_id
                                AND sl2.land_id <> sl.land_id
                          )
                    );
                END IF;

                IF kind_a = 'property' AND kind_b = 'land' THEN
                    RETURN terra_commercial_targets_conflict(
                        kind_b, id_b, kind_a, id_a
                    );
                END IF;

                IF kind_a = 'land' AND kind_b = 'unit' THEN
                    RETURN EXISTS (
                        SELECT 1
                        FROM units u
                        JOIN buildings b
                            ON b.id = u.building_id
                        JOIN property_parcels pp
                            ON pp.property_id = b.property_id
                        JOIN parcels p
                            ON p.id = pp.parcel_id
                        JOIN ilots i
                            ON i.id = p.ilot_id
                        JOIN subdivision_lands sl
                            ON sl.subdivision_id = i.subdivision_id
                        WHERE u.id = id_b
                          AND sl.land_id = id_a
                          AND NOT EXISTS (
                              SELECT 1
                              FROM subdivision_lands sl2
                              WHERE sl2.subdivision_id = sl.subdivision_id
                                AND sl2.land_id <> sl.land_id
                          )
                    );
                END IF;

                IF kind_a = 'unit' AND kind_b = 'land' THEN
                    RETURN terra_commercial_targets_conflict(
                        kind_b, id_b, kind_a, id_a
                    );
                END IF;

                RETURN FALSE;
            END;
            $function$
            SQL);

        // The returned keys form the conservative locking surface. A shared
        // property key deliberately serializes sibling units while the
        // conflict predicate still allows both units to coexist commercially.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_commercial_offer_scope_keys(
                p_offer_id TEXT
            )
            RETURNS TABLE(lock_key TEXT)
            LANGUAGE sql
            STABLE
            AS $function$
            WITH direct AS (
                SELECT land_id, parcel_id, property_id, unit_id
                FROM commercial_offer_items
                WHERE commercial_offer_id = p_offer_id
            ),
            offer_properties AS (
                SELECT property_id
                FROM direct
                WHERE property_id IS NOT NULL

                UNION

                SELECT pp.property_id
                FROM direct d
                JOIN property_parcels pp ON pp.parcel_id = d.parcel_id

                UNION

                SELECT b.property_id
                FROM direct d
                JOIN units u ON u.id = d.unit_id
                JOIN buildings b ON b.id = u.building_id
            ),
            offer_parcels AS (
                SELECT parcel_id
                FROM direct
                WHERE parcel_id IS NOT NULL

                UNION

                SELECT pp.parcel_id
                FROM property_parcels pp
                JOIN offer_properties op ON op.property_id = pp.property_id
            ),
            offer_units AS (
                SELECT unit_id
                FROM direct
                WHERE unit_id IS NOT NULL
            ),
            offer_lands AS (
                SELECT land_id
                FROM direct
                WHERE land_id IS NOT NULL

                UNION

                SELECT sl.land_id
                FROM offer_parcels op
                JOIN parcels p ON p.id = op.parcel_id
                JOIN ilots i ON i.id = p.ilot_id
                JOIN subdivision_lands sl
                    ON sl.subdivision_id = i.subdivision_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM subdivision_lands sl2
                    WHERE sl2.subdivision_id = sl.subdivision_id
                      AND sl2.land_id <> sl.land_id
                )
            )
            SELECT DISTINCT 'land:' || land_id::text FROM offer_lands
            UNION
            SELECT DISTINCT 'parcel:' || parcel_id::text FROM offer_parcels
            UNION
            SELECT DISTINCT 'property:' || property_id::text FROM offer_properties
            UNION
            SELECT DISTINCT 'unit:' || unit_id::text FROM offer_units
            UNION
            SELECT DISTINCT 'property:' || property_id::text FROM offer_properties
            ORDER BY 1
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_guard_hierarchical_reservation_conflicts()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                key_value TEXT;
                new_target RECORD;
                existing_target RECORD;
            BEGIN
                IF NEW.status <> 'confirmed' THEN
                    RETURN NEW;
                END IF;

                FOR key_value IN
                    SELECT lock_key
                    FROM terra_commercial_offer_scope_keys(NEW.commercial_offer_id)
                    ORDER BY lock_key
                LOOP
                    PERFORM pg_advisory_xact_lock(
                        hashtextextended(key_value, 0)
                    );
                END LOOP;

                FOR new_target IN
                    SELECT kind, target_id
                    FROM (
                        SELECT 'land' AS kind, land_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND land_id IS NOT NULL

                        UNION ALL
                        SELECT 'parcel', parcel_id::text
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND parcel_id IS NOT NULL

                        UNION ALL
                        SELECT 'property', property_id::text
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND property_id IS NOT NULL

                        UNION ALL
                        SELECT 'unit', unit_id::text
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = NEW.commercial_offer_id
                          AND unit_id IS NOT NULL
                    ) AS targets
                    ORDER BY kind, target_id
                LOOP
                    FOR existing_target IN
                        SELECT existing_item.*,
                               CASE
                                   WHEN existing_item.land_id IS NOT NULL THEN 'land'
                                   WHEN existing_item.parcel_id IS NOT NULL THEN 'parcel'
                                   WHEN existing_item.property_id IS NOT NULL THEN 'property'
                                   ELSE 'unit'
                               END AS existing_kind,
                               COALESCE(
                                   existing_item.land_id,
                                   existing_item.parcel_id,
                                   existing_item.property_id,
                                   existing_item.unit_id
                               )::text AS existing_target_id
                        FROM reservations existing_reservation
                        JOIN commercial_offer_items existing_item
                          ON existing_item.commercial_offer_id = existing_reservation.commercial_offer_id
                        WHERE existing_reservation.status IN ('confirmed', 'converted')
                          AND existing_reservation.id <> NEW.id
                    LOOP
                        IF terra_commercial_targets_conflict(
                            new_target.kind,
                            new_target.target_id,
                            existing_target.existing_kind,
                            existing_target.existing_target_id
                        ) THEN
                            RAISE EXCEPTION
                                'A committed reservation already conflicts with target %:% of commercial offer %.',
                                new_target.kind,
                                new_target.target_id,
                                NEW.commercial_offer_id
                                USING ERRCODE = '23505';
                        END IF;
                    END LOOP;
                END LOOP;

                RETURN NEW;
            END;
            $function$
            SQL);

        // Named with 000 so this guard acquires the superset of advisory
        // locks before the historical 5B/5C exact-target triggers execute.
        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_000_hierarchical_reservation_conflict_guard
            BEFORE INSERT OR UPDATE OF status, commercial_offer_id
            ON reservations
            FOR EACH ROW
            EXECUTE FUNCTION terra_guard_hierarchical_reservation_conflicts()
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_guard_hierarchical_sale_conflicts()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                offer_id TEXT;
                key_value TEXT;
                new_target RECORD;
                existing_target RECORD;
            BEGIN
                IF NEW.status NOT IN ('confirmed', 'completed') THEN
                    RETURN NEW;
                END IF;

                SELECT commercial_offer_id
                INTO offer_id
                FROM reservations
                WHERE id = NEW.reservation_id;

                IF NOT FOUND THEN
                    RETURN NEW;
                END IF;

                FOR key_value IN
                    SELECT lock_key
                    FROM terra_commercial_offer_scope_keys(offer_id)
                    ORDER BY lock_key
                LOOP
                    PERFORM pg_advisory_xact_lock(
                        hashtextextended(key_value, 0)
                    );
                END LOOP;

                FOR new_target IN
                    SELECT kind, target_id
                    FROM (
                        SELECT 'land' AS kind, land_id::text AS target_id
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = offer_id
                          AND land_id IS NOT NULL

                        UNION ALL
                        SELECT 'parcel', parcel_id::text
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = offer_id
                          AND parcel_id IS NOT NULL

                        UNION ALL
                        SELECT 'property', property_id::text
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = offer_id
                          AND property_id IS NOT NULL

                        UNION ALL
                        SELECT 'unit', unit_id::text
                        FROM commercial_offer_items
                        WHERE commercial_offer_id = offer_id
                          AND unit_id IS NOT NULL
                    ) AS targets
                    ORDER BY kind, target_id
                LOOP
                    FOR existing_target IN
                        SELECT existing_item.*,
                               CASE
                                   WHEN existing_item.land_id IS NOT NULL THEN 'land'
                                   WHEN existing_item.parcel_id IS NOT NULL THEN 'parcel'
                                   WHEN existing_item.property_id IS NOT NULL THEN 'property'
                                   ELSE 'unit'
                               END AS existing_kind,
                               COALESCE(
                                   existing_item.land_id,
                                   existing_item.parcel_id,
                                   existing_item.property_id,
                                   existing_item.unit_id
                               )::text AS existing_target_id
                        FROM sales existing_sale
                        JOIN reservations existing_reservation
                          ON existing_reservation.id = existing_sale.reservation_id
                        JOIN commercial_offer_items existing_item
                          ON existing_item.commercial_offer_id = existing_reservation.commercial_offer_id
                        WHERE existing_sale.status IN ('confirmed', 'completed')
                          AND existing_sale.id <> NEW.id
                    LOOP
                        IF terra_commercial_targets_conflict(
                            new_target.kind,
                            new_target.target_id,
                            existing_target.existing_kind,
                            existing_target.existing_target_id
                        ) THEN
                            RAISE EXCEPTION
                                'A committed sale already conflicts with target %:% of reservation %.',
                                new_target.kind,
                                new_target.target_id,
                                NEW.reservation_id
                                USING ERRCODE = '23505';
                        END IF;
                    END LOOP;
                END LOOP;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_000_hierarchical_sale_conflict_guard
            BEFORE INSERT OR UPDATE OF reservation_id, status
            ON sales
            FOR EACH ROW
            EXECUTE FUNCTION terra_guard_hierarchical_sale_conflicts()
            SQL);
    }

    private function addFinancialAllocationGuards(): void
    {
        // Only confirmed incoming payments may be allocated, and an invoice
        // must be in a collectible state.
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
                WHERE id = NEW.payment_id;

                IF payment_status IS NULL THEN
                    RAISE EXCEPTION
                        'Payment % does not exist.',
                        NEW.payment_id
                        USING ERRCODE = '23503';
                END IF;

                IF payment_status <> 'confirmed' OR payment_direction <> 'incoming' THEN
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

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_000_validate_payment_allocation_state
            BEFORE INSERT OR UPDATE OF payment_id, invoice_id
            ON payment_allocations
            FOR EACH ROW
            EXECUTE FUNCTION terra_validate_payment_allocation_state()
            SQL);

        // Once a payment has allocations, its status remains confirmed. The
        // correction path is a refund/reversal transaction, not a mutation of
        // the allocated payment itself.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_protect_allocated_payment_status()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF NEW.status IS DISTINCT FROM OLD.status
                   AND EXISTS (
                       SELECT 1
                       FROM payment_allocations
                       WHERE payment_id = NEW.id
                   )
                   AND NEW.status <> 'confirmed'
                THEN
                    RAISE EXCEPTION
                        'Payment % has allocations and must remain confirmed.',
                        NEW.id
                        USING ERRCODE = '55006';
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_000_protect_allocated_payment_status
            BEFORE UPDATE OF status
            ON payments
            FOR EACH ROW
            EXECUTE FUNCTION terra_protect_allocated_payment_status()
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_000_protect_allocated_payment_status ON payments'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_protect_allocated_payment_status()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_000_validate_payment_allocation_state ON payment_allocations'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_payment_allocation_state()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_000_hierarchical_sale_conflict_guard ON sales'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_guard_hierarchical_sale_conflicts()');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_000_hierarchical_reservation_conflict_guard ON reservations'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_guard_hierarchical_reservation_conflicts()');
        DB::statement('DROP FUNCTION IF EXISTS terra_commercial_offer_scope_keys(TEXT)');
        DB::statement('DROP FUNCTION IF EXISTS terra_commercial_targets_conflict(TEXT, TEXT, TEXT, TEXT)');

        DB::statement(
            'DROP TRIGGER IF EXISTS terra_validate_reservation_offer_integrity_trigger ON reservations'
        );
        DB::statement('DROP FUNCTION IF EXISTS terra_validate_reservation_offer_integrity()');

        DB::statement('DROP INDEX IF EXISTS reservations_committed_offer_unique');

        DB::statement(
            'ALTER TABLE subdivision_lands DROP CONSTRAINT IF EXISTS subdivision_lands_operation_land_fk'
        );
        DB::statement(
            'ALTER TABLE subdivision_lands DROP CONSTRAINT IF EXISTS subdivision_lands_operation_subdivision_fk'
        );
        DB::statement(
            'ALTER TABLE subdivisions DROP CONSTRAINT IF EXISTS subdivisions_operation_id_id_unique'
        );

        Schema::table('subdivision_lands', function (Blueprint $table): void {
            $table->dropColumn('operation_id');
        });

        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropColumn([
                'lot_number',
                'cadastral_reference',
            ]);
        });
    }
};
