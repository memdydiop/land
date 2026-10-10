<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

function terraLandLineageParty(string $name): string
{
    $id = (string) Str::ulid();

    DB::table('parties')->insert([
        'id' => $id,
        'type' => 'person',
        'display_name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function terraLandLineageOffer(string $reference): string
{
    $id = (string) Str::ulid();

    DB::table('commercial_offers')->insert([
        'id' => $id,
        'reference' => $reference.'-'.Str::upper(Str::random(8)),
        'title' => $reference,
        'price' => '10000000.0000',
        'currency' => 'XOF',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function terraLandLineageReservation(
    string $offerId,
    string $partyId,
): string {
    $id = (string) Str::ulid();

    DB::table('reservations')->insert([
        'id' => $id,
        'commercial_offer_id' => $offerId,
        'party_id' => $partyId,
        'reference' => 'RES-'.Str::upper(Str::random(10)),
        'status' => 'pending',
        'reserved_at' => now(),
        'expires_at' => now()->addDays(7),
        'agreed_price' => '10000000.0000',
        'deposit_amount' => '500000.0000',
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function terraLandLineageFixture(): array
{
    $operationId = (string) Str::ulid();
    $subdivisionId = (string) Str::ulid();
    $landId = (string) Str::ulid();
    $ilotId = (string) Str::ulid();
    $parcelId = (string) Str::ulid();
    $propertyOneId = (string) Str::ulid();
    $propertyTwoId = (string) Str::ulid();
    $buildingId = (string) Str::ulid();
    $unitId = (string) Str::ulid();

    DB::table('operations')->insert([
        'id' => $operationId,
        'reference' => 'OP-'.Str::upper(Str::random(10)),
        'name' => 'Land lineage test',
        'type' => 'land_development',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('lands')->insert([
        'id' => $landId,
        'reference' => 'LAND-'.Str::upper(Str::random(10)),
        'name' => 'Land lineage test',
        'status' => 'acquired',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('operation_lands')->insert([
        'id' => (string) Str::ulid(),
        'operation_id' => $operationId,
        'land_id' => $landId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('subdivisions')->insert([
        'id' => $subdivisionId,
        'operation_id' => $operationId,
        'reference' => 'SUB-'.Str::upper(Str::random(10)),
        'name' => 'Land lineage subdivision',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('subdivision_lands')->insert([
        'id' => (string) Str::ulid(),
        'subdivision_id' => $subdivisionId,
        'operation_id' => $operationId,
        'land_id' => $landId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ilots')->insert([
        'id' => $ilotId,
        'subdivision_id' => $subdivisionId,
        'reference' => 'ILOT-'.Str::upper(Str::random(10)),
        'name' => 'Land lineage ilot',
        'status' => 'planned',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('parcels')->insert([
        'id' => $parcelId,
        'ilot_id' => $ilotId,
        'land_id' => $landId,
        'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
        'parcel_number' => 'P-'.Str::upper(Str::random(8)),
        'status' => 'provisional',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([$propertyOneId, $propertyTwoId] as $propertyId) {
        DB::table('properties')->insert([
            'id' => $propertyId,
            'operation_id' => $operationId,
            'reference' => 'PROPERTY-'.Str::upper(Str::random(10)),
            'name' => 'Land lineage property',
            'type' => 'residential',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_parcels')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'parcel_id' => $parcelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('buildings')->insert([
        'id' => $buildingId,
        'property_id' => $propertyOneId,
        'name' => 'Land lineage building',
        'reference' => 'BUILDING-'.Str::upper(Str::random(10)),
        'status' => 'planned',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('units')->insert([
        'id' => $unitId,
        'building_id' => $buildingId,
        'reference' => 'UNIT-'.Str::upper(Str::random(10)),
        'unit_type' => 'apartment',
        'status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact(
        'landId',
        'parcelId',
        'propertyOneId',
        'propertyTwoId',
        'unitId',
    );
}

function terraLandLineageOfferItem(string $offerId, array $target): void
{
    DB::table('commercial_offer_items')->insert([
        'id' => (string) Str::ulid(),
        'commercial_offer_id' => $offerId,
        'position' => 0,
        ...$target,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('land conflicts with parcel through explicit parcel land lineage', function () {
    $tenant = $this->testTenant('terra_land_lineage_parcel_conflict');

    $tenant->run(function (): void {
        $fixture = terraLandLineageFixture();

        $landOffer = terraLandLineageOffer('LAND-CONFLICT');
        terraLandLineageOfferItem($landOffer, [
            'land_id' => $fixture['landId'],
        ]);

        $parcelOffer = terraLandLineageOffer('PARCEL-CONFLICT');
        terraLandLineageOfferItem($parcelOffer, [
            'parcel_id' => $fixture['parcelId'],
        ]);

        $buyerOne = terraLandLineageParty('Land buyer');
        $buyerTwo = terraLandLineageParty('Parcel buyer');

        $reservationOne = terraLandLineageReservation($landOffer, $buyerOne);
        $reservationTwo = terraLandLineageReservation($parcelOffer, $buyerTwo);

        DB::table('reservations')
            ->where('id', $reservationOne)
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwo)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('land conflicts with property through parcel lineage', function () {
    $tenant = $this->testTenant('terra_land_lineage_property_conflict');

    $tenant->run(function (): void {
        $fixture = terraLandLineageFixture();

        $landOffer = terraLandLineageOffer('LAND-PROPERTY-CONFLICT');
        terraLandLineageOfferItem($landOffer, [
            'land_id' => $fixture['landId'],
        ]);

        $propertyOffer = terraLandLineageOffer('PROPERTY-CONFLICT');
        terraLandLineageOfferItem($propertyOffer, [
            'property_id' => $fixture['propertyOneId'],
        ]);

        $buyerOne = terraLandLineageParty('Land property buyer');
        $buyerTwo = terraLandLineageParty('Property buyer');

        $reservationOne = terraLandLineageReservation($landOffer, $buyerOne);
        $reservationTwo = terraLandLineageReservation($propertyOffer, $buyerTwo);

        DB::table('reservations')
            ->where('id', $reservationOne)
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwo)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('land conflicts with unit through property parcel lineage', function () {
    $tenant = $this->testTenant('terra_land_lineage_unit_conflict');

    $tenant->run(function (): void {
        $fixture = terraLandLineageFixture();

        $landOffer = terraLandLineageOffer('LAND-UNIT-CONFLICT');
        terraLandLineageOfferItem($landOffer, [
            'land_id' => $fixture['landId'],
        ]);

        $unitOffer = terraLandLineageOffer('UNIT-CONFLICT');
        terraLandLineageOfferItem($unitOffer, [
            'unit_id' => $fixture['unitId'],
        ]);

        $buyerOne = terraLandLineageParty('Land unit buyer');
        $buyerTwo = terraLandLineageParty('Unit buyer');

        $reservationOne = terraLandLineageReservation($landOffer, $buyerOne);
        $reservationTwo = terraLandLineageReservation($unitOffer, $buyerTwo);

        DB::table('reservations')
            ->where('id', $reservationOne)
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwo)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('property conflicts with property when they share a parcel', function () {
    $tenant = $this->testTenant('terra_land_lineage_property_property');

    $tenant->run(function (): void {
        $fixture = terraLandLineageFixture();

        $offerOne = terraLandLineageOffer('PROPERTY-ONE');
        $offerTwo = terraLandLineageOffer('PROPERTY-TWO');

        terraLandLineageOfferItem($offerOne, [
            'property_id' => $fixture['propertyOneId'],
        ]);

        terraLandLineageOfferItem($offerTwo, [
            'property_id' => $fixture['propertyTwoId'],
        ]);

        $buyerOne = terraLandLineageParty('Property one buyer');
        $buyerTwo = terraLandLineageParty('Property two buyer');

        $reservationOne = terraLandLineageReservation($offerOne, $buyerOne);
        $reservationTwo = terraLandLineageReservation($offerTwo, $buyerTwo);

        DB::table('reservations')
            ->where('id', $reservationOne)
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwo)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('ambiguous parcel lineage is treated as a land conflict', function () {
    $tenant = $this->testTenant('terra_land_lineage_ambiguous');

    $tenant->run(function (): void {
        $fixture = terraLandLineageFixture();

        $secondLandId = (string) Str::ulid();
        $operationId = $fixture['operationId'] ?? DB::table('operation_lands')
            ->where('land_id', $fixture['landId'])
            ->value('operation_id');

        DB::table('lands')->insert([
            'id' => $secondLandId,
            'reference' => 'LAND-'.Str::upper(Str::random(10)),
            'name' => 'Second land',
            'status' => 'acquired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('operation_lands')->insert([
            'id' => (string) Str::ulid(),
            'operation_id' => $operationId,
            'land_id' => $secondLandId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subdivisionId = DB::table('ilots')
            ->where('id', DB::table('parcels')
                ->where('id', $fixture['parcelId'])
                ->value('ilot_id'))
            ->value('subdivision_id');

        DB::table('subdivision_lands')->insert([
            'id' => (string) Str::ulid(),
            'subdivision_id' => $subdivisionId,
            'operation_id' => $operationId,
            'land_id' => $secondLandId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parcels')
            ->where('id', $fixture['parcelId'])
            ->update(['land_id' => null]);

        $landOffer = terraLandLineageOffer('AMBIGUOUS-LAND');
        terraLandLineageOfferItem($landOffer, [
            'land_id' => $fixture['landId'],
        ]);

        $parcelOffer = terraLandLineageOffer('AMBIGUOUS-PARCEL');
        terraLandLineageOfferItem($parcelOffer, [
            'parcel_id' => $fixture['parcelId'],
        ]);

        $buyerOne = terraLandLineageParty('Ambiguous land buyer');
        $buyerTwo = terraLandLineageParty('Ambiguous parcel buyer');

        $reservationOne = terraLandLineageReservation($landOffer, $buyerOne);
        $reservationTwo = terraLandLineageReservation($parcelOffer, $buyerTwo);

        DB::table('reservations')
            ->where('id', $reservationOne)
            ->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwo)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('consolidated hierarchical reservation and sale guards are installed', function () {
    $tenant = $this->testTenant('terra_land_lineage_sale_guards');

    $tenant->run(function (): void {
        $guards = DB::select(<<<'SQL'
            SELECT c.relname, t.tgname
            FROM pg_trigger AS t
            JOIN pg_class AS c ON c.oid = t.tgrelid
            JOIN pg_namespace AS n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema()
              AND c.relname IN ('reservations', 'sales')
              AND NOT t.tgisinternal
              AND t.tgname IN (
                  'terra_000_hierarchical_reservation_conflict_guard',
                  'terra_000_hierarchical_sale_conflict_guard'
              )
            ORDER BY c.relname, t.tgname
            SQL
        );

        expect(array_map(
            static fn ($guard) => [$guard->relname, $guard->tgname],
            $guards,
        ))->toBe([
            [
                'reservations',
                'terra_000_hierarchical_reservation_conflict_guard',
            ],
            [
                'sales',
                'terra_000_hierarchical_sale_conflict_guard',
            ],
        ]);
    });
});

test('land and parcel offers share their known lineage advisory lock', function () {
    $tenant = $this->testTenant('terra_land_lineage_concurrency');

    $tenant->run(function (): void {
        $fixture = terraLandLineageFixture();

        $landOffer = terraLandLineageOffer('CONCURRENCY-LAND');
        terraLandLineageOfferItem($landOffer, [
            'land_id' => $fixture['landId'],
        ]);

        $parcelOffer = terraLandLineageOffer('CONCURRENCY-PARCEL');
        terraLandLineageOfferItem($parcelOffer, [
            'parcel_id' => $fixture['parcelId'],
        ]);

        $landKeys = collect(DB::select(
            'SELECT lock_key FROM terra_commercial_offer_scope_keys(?)',
            [$landOffer],
        ))->pluck('lock_key')->all();

        $parcelKeys = collect(DB::select(
            'SELECT lock_key FROM terra_commercial_offer_scope_keys(?)',
            [$parcelOffer],
        ))->pluck('lock_key')->all();

        expect(array_intersect($landKeys, $parcelKeys))
            ->toContain('land:'.$fixture['landId']);

        $first = DB::connection();
        $second = DB::connection('pgsql');

        $first->beginTransaction();
        $second->beginTransaction();

        try {
            $first->selectOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                ['land:'.$fixture['landId']],
            );

            $blocked = $second->selectOne(
                'SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS locked',
                ['land:'.$fixture['landId']],
            );

            expect($blocked->locked)->toBeFalse();

            $first->commit();

            $acquired = $second->selectOne(
                'SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS locked',
                ['land:'.$fixture['landId']],
            );

            expect($acquired->locked)->toBeTrue();
        } finally {
            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
        }
    });
});
