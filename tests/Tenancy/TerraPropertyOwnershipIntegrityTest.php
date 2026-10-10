<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

function createOwnershipTestProperty(): array
{
    $propertyId = (string) Str::ulid();
    $partyAId = (string) Str::ulid();
    $partyBId = (string) Str::ulid();
    $partyCId = (string) Str::ulid();

    DB::table('properties')->insert([
        'id' => $propertyId,
        'operation_id' => null,
        'reference' => 'PROPERTY-'.Str::upper(Str::random(10)),
        'name' => 'Ownership temporal test',
        'type' => 'residential',
        'status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([
        [$partyAId, 'Party A'],
        [$partyBId, 'Party B'],
        [$partyCId, 'Party C'],
    ] as [$id, $name]) {
        DB::table('parties')->insert([
            'id' => $id,
            'type' => 'person',
            'display_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return [$propertyId, $partyAId, $partyBId, $partyCId];
}

test('property ownership is validated across future periods', function () {
    $tenant = $this->testTenant('terra_property_ownership_temporal');

    $tenant->run(function (): void {
        [$propertyId, $partyAId, $partyBId, $partyCId] = createOwnershipTestProperty();

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 60,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyBId,
            'ownership_percentage' => 40,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyCId,
            'ownership_percentage' => 1,
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('non-overlapping historical and future ownership periods are allowed', function () {
    $tenant = $this->testTenant('terra_property_ownership_history');

    $tenant->run(function (): void {
        [$propertyId, $partyAId] = createOwnershipTestProperty();

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 100,
            'start_date' => null,
            'end_date' => '2025-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 100,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('property_owners')
                ->where('property_id', $propertyId)
                ->count()
        )->toBe(2);
    });
});

test('the same party cannot have overlapping ownership periods', function () {
    $tenant = $this->testTenant('terra_property_ownership_party_overlap');

    $tenant->run(function (): void {
        [$propertyId, $partyAId] = createOwnershipTestProperty();

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 30,
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 30,
            'start_date' => '2027-06-30',
            'end_date' => '2027-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 30,
            'start_date' => '2027-07-01',
            'end_date' => '2027-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('property_owners')
                ->where('property_id', $propertyId)
                ->count()
        )->toBe(2);
    });
});

test('ownership update is also validated temporally', function () {
    $tenant = $this->testTenant('terra_property_ownership_update');

    $tenant->run(function (): void {
        [$propertyId, $partyAId, $partyBId, $partyCId] = createOwnershipTestProperty();

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyAId,
            'ownership_percentage' => 60,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_owners')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'party_id' => $partyBId,
            'ownership_percentage' => 40,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownershipId = (string) Str::ulid();

        DB::table('property_owners')->insert([
            'id' => $ownershipId,
            'property_id' => $propertyId,
            'party_id' => $partyCId,
            'ownership_percentage' => 1,
            'start_date' => '2028-01-01',
            'end_date' => '2028-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('property_owners')
            ->where('id', $ownershipId)
            ->update([
                'start_date' => '2027-06-01',
                'updated_at' => now(),
            ]))->toThrow(QueryException::class);
    });
});
