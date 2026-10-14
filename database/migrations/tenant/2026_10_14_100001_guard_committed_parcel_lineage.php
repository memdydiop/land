<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_guard_committed_parcel_lineage()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                affected_land_ids TEXT[];
                lock_key TEXT;
                held_target RECORD;
            BEGIN
                IF NEW.land_id IS NOT DISTINCT FROM OLD.land_id
                   AND NEW.ilot_id IS NOT DISTINCT FROM OLD.ilot_id
                THEN
                    RETURN NEW;
                END IF;

                /*
                 * Collect explicit and inferred Land lineage from both
                 * the previous and the proposed parcel assignment.
                 */
                SELECT COALESCE(
                    array_agg(DISTINCT candidates.land_id),
                    ARRAY[]::TEXT[]
                )
                INTO affected_land_ids
                FROM (
                    SELECT OLD.land_id::text AS land_id
                    WHERE OLD.land_id IS NOT NULL

                    UNION

                    SELECT NEW.land_id::text
                    WHERE NEW.land_id IS NOT NULL

                    UNION

                    SELECT sl.land_id::text
                    FROM ilots AS i
                    JOIN subdivision_lands AS sl
                      ON sl.subdivision_id = i.subdivision_id
                    WHERE i.id IN (OLD.ilot_id, NEW.ilot_id)
                ) AS candidates;

                /*
                 * Use the same advisory-lock namespace as the commercial
                 * guards. Sorted acquisition avoids lock-order inversions.
                 */
                FOR lock_key IN
                    SELECT locks.lock_key
                    FROM (
                        SELECT 'parcel:' || OLD.id::text AS lock_key

                        UNION

                        SELECT 'land:' || affected.land_id
                        FROM unnest(affected_land_ids)
                             AS affected(land_id)

                        UNION

                        SELECT 'terra:land-lineage-ambiguity'
                    ) AS locks
                    ORDER BY locks.lock_key
                LOOP
                    PERFORM pg_advisory_xact_lock(
                        hashtextextended(lock_key, 0)
                    );
                END LOOP;

                /*
                 * A parcel cannot change its Land/Ilot lineage when a
                 * committed reservation or sale depends on the affected
                 * parcel, Land, Property or Unit.
                 */
                FOR held_target IN
                    WITH committed_offers AS (
                        SELECT r.commercial_offer_id
                        FROM reservations AS r
                        WHERE r.status IN ('confirmed', 'converted')

                        UNION

                        SELECT r.commercial_offer_id
                        FROM sales AS s
                        JOIN reservations AS r
                          ON r.id = s.reservation_id
                        WHERE s.status IN ('confirmed', 'completed')
                    ),
                    all_targets AS (
                        SELECT 'land'::text AS kind, item.land_id::text AS target_id
                        FROM commercial_offer_items AS item
                        WHERE item.land_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id
                              FROM committed_offers
                          )

                        UNION ALL

                        SELECT 'parcel', item.parcel_id::text
                        FROM commercial_offer_items AS item
                        WHERE item.parcel_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id
                              FROM committed_offers
                          )

                        UNION ALL

                        SELECT 'property', item.property_id::text
                        FROM commercial_offer_items AS item
                        WHERE item.property_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id
                              FROM committed_offers
                          )

                        UNION ALL

                        SELECT 'unit', item.unit_id::text
                        FROM commercial_offer_items AS item
                        WHERE item.unit_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id
                              FROM committed_offers
                          )
                    )
                    SELECT DISTINCT kind, target_id
                    FROM all_targets
                LOOP
                    IF (
                        held_target.kind = 'parcel'
                        AND held_target.target_id = OLD.id::text
                    )
                    OR (
                        held_target.kind = 'land'
                        AND held_target.target_id = ANY(affected_land_ids)
                    )
                    OR terra_commercial_targets_conflict(
                        held_target.kind,
                        held_target.target_id,
                        'parcel',
                        OLD.id::text
                    )
                    THEN
                        RAISE EXCEPTION
                            'Parcel % lineage cannot change because a committed commercial target depends on it.',
                            OLD.id
                            USING ERRCODE = '55006';
                    END IF;
                END LOOP;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_000_guard_committed_parcel_lineage
            BEFORE UPDATE OF land_id, ilot_id
            ON parcels
            FOR EACH ROW
            EXECUTE FUNCTION terra_guard_committed_parcel_lineage()
            SQL);


        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION terra_guard_committed_subdivision_land_lineage()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                affected_subdivision_ids TEXT[];
                affected_land_ids TEXT[];
                affected_parcel_ids TEXT[];
                affected_property_ids TEXT[];
                affected_unit_ids TEXT[];
                lock_key TEXT;
                held_target RECORD;
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND ROW(NEW.subdivision_id, NEW.operation_id, NEW.land_id)
                       IS NOT DISTINCT FROM
                       ROW(OLD.subdivision_id, OLD.operation_id, OLD.land_id)
                THEN
                    RETURN NEW;
                END IF;

                IF TG_OP = 'INSERT' THEN
                    affected_subdivision_ids := ARRAY[NEW.subdivision_id::text];
                    affected_land_ids := ARRAY[NEW.land_id::text];
                ELSIF TG_OP = 'DELETE' THEN
                    affected_subdivision_ids := ARRAY[OLD.subdivision_id::text];
                    affected_land_ids := ARRAY[OLD.land_id::text];
                ELSE
                    affected_subdivision_ids := ARRAY[
                        OLD.subdivision_id::text,
                        NEW.subdivision_id::text
                    ];
                    affected_land_ids := ARRAY[
                        OLD.land_id::text,
                        NEW.land_id::text
                    ];
                END IF;

                /*
                 * Include every Land associated with either subdivision,
                 * along with the old and proposed Land references.
                 */
                SELECT COALESCE(
                    array_agg(DISTINCT related.land_id),
                    ARRAY[]::TEXT[]
                )
                INTO affected_land_ids
                FROM (
                    SELECT unnest(affected_land_ids) AS land_id

                    UNION

                    SELECT sl.land_id::text
                    FROM subdivision_lands AS sl
                    WHERE sl.subdivision_id::text =
                          ANY(affected_subdivision_ids)
                ) AS related
                WHERE related.land_id IS NOT NULL;

                /*
                 * Include parcels in the affected subdivisions and parcels
                 * explicitly attached to any affected Land.
                 */
                SELECT COALESCE(
                    array_agg(DISTINCT p.id::text),
                    ARRAY[]::TEXT[]
                )
                INTO affected_parcel_ids
                FROM parcels AS p
                LEFT JOIN ilots AS i ON i.id = p.ilot_id
                WHERE i.subdivision_id::text = ANY(affected_subdivision_ids)
                   OR p.land_id::text = ANY(affected_land_ids);

                SELECT COALESCE(
                    array_agg(DISTINCT related.land_id),
                    ARRAY[]::TEXT[]
                )
                INTO affected_land_ids
                FROM (
                    SELECT unnest(affected_land_ids) AS land_id

                    UNION

                    SELECT p.land_id::text
                    FROM parcels AS p
                    WHERE p.id::text = ANY(affected_parcel_ids)
                      AND p.land_id IS NOT NULL
                ) AS related
                WHERE related.land_id IS NOT NULL;

                SELECT COALESCE(
                    array_agg(DISTINCT pp.property_id::text),
                    ARRAY[]::TEXT[]
                )
                INTO affected_property_ids
                FROM property_parcels AS pp
                WHERE pp.parcel_id::text = ANY(affected_parcel_ids);

                SELECT COALESCE(
                    array_agg(DISTINCT u.id::text),
                    ARRAY[]::TEXT[]
                )
                INTO affected_unit_ids
                FROM units AS u
                JOIN buildings AS b ON b.id = u.building_id
                WHERE b.property_id::text = ANY(affected_property_ids);

                /*
                 * Take the same sorted advisory-lock keys as the
                 * reservation and sale conflict guards.
                 */
                FOR lock_key IN
                    SELECT locks.lock_key
                    FROM (
                        SELECT 'terra:land-lineage-ambiguity' AS lock_key

                        UNION
                        SELECT 'land:' || target_id
                        FROM unnest(affected_land_ids) AS ids(target_id)

                        UNION
                        SELECT 'parcel:' || target_id
                        FROM unnest(affected_parcel_ids) AS ids(target_id)

                        UNION
                        SELECT 'property:' || target_id
                        FROM unnest(affected_property_ids) AS ids(target_id)

                        UNION
                        SELECT 'unit:' || target_id
                        FROM unnest(affected_unit_ids) AS ids(target_id)
                    ) AS locks
                    ORDER BY locks.lock_key
                LOOP
                    PERFORM pg_advisory_xact_lock(
                        hashtextextended(lock_key, 0)
                    );
                END LOOP;

                /*
                 * Freeze lineage after a reservation is confirmed or converted,
                 * or a linked sale is confirmed or completed.
                 */
                FOR held_target IN
                    WITH active_offers AS (
                        SELECT r.commercial_offer_id
                        FROM reservations AS r
                        WHERE r.status IN ('confirmed', 'converted')

                        UNION

                        SELECT r.commercial_offer_id
                        FROM sales AS s
                        JOIN reservations AS r
                          ON r.id = s.reservation_id
                        WHERE s.status IN ('confirmed', 'completed')
                    ),
                    all_targets AS (
                        SELECT 'land'::TEXT AS kind, item.land_id::TEXT AS target_id
                        FROM commercial_offer_items AS item
                        WHERE item.land_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id FROM active_offers
                          )

                        UNION ALL

                        SELECT 'parcel', item.parcel_id::TEXT
                        FROM commercial_offer_items AS item
                        WHERE item.parcel_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id FROM active_offers
                          )

                        UNION ALL

                        SELECT 'property', item.property_id::TEXT
                        FROM commercial_offer_items AS item
                        WHERE item.property_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id FROM active_offers
                          )

                        UNION ALL

                        SELECT 'unit', item.unit_id::TEXT
                        FROM commercial_offer_items AS item
                        WHERE item.unit_id IS NOT NULL
                          AND item.commercial_offer_id IN (
                              SELECT commercial_offer_id FROM active_offers
                          )
                    )
                    SELECT DISTINCT kind, target_id
                    FROM all_targets
                LOOP
                    IF (
                        held_target.kind = 'land'
                        AND held_target.target_id = ANY(affected_land_ids)
                    )
                    OR (
                        held_target.kind = 'parcel'
                        AND held_target.target_id = ANY(affected_parcel_ids)
                    )
                    OR (
                        held_target.kind = 'property'
                        AND held_target.target_id = ANY(affected_property_ids)
                    )
                    OR (
                        held_target.kind = 'unit'
                        AND held_target.target_id = ANY(affected_unit_ids)
                    )
                    THEN
                        RAISE EXCEPTION
                            'Subdivision Land lineage cannot change while an active commercial reservation or sale depends on it.'
                            USING ERRCODE = '55006';
                    END IF;
                END LOOP;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $function$
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER terra_000_guard_committed_subdivision_land_lineage
            BEFORE INSERT OR UPDATE OR DELETE
            ON subdivision_lands
            FOR EACH ROW
            EXECUTE FUNCTION terra_guard_committed_subdivision_land_lineage()
            SQL);

    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_000_guard_committed_parcel_lineage ON parcels'
        );
        DB::statement(
            'DROP TRIGGER IF EXISTS terra_000_guard_committed_subdivision_land_lineage ON subdivision_lands'
        );

        DB::statement(
            'DROP FUNCTION IF EXISTS terra_guard_committed_subdivision_land_lineage()'
        );



        DB::statement(
            'DROP FUNCTION IF EXISTS terra_guard_committed_parcel_lineage()'
        );
    }
};
