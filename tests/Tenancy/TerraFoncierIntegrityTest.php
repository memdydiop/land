<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('parcel can exist without an ilot and rejects commercial status values', function () {
    $tenant = $this->testTenant('terra_foncier_parcel');

    $tenant->run(function (): void {
        DB::table('parcels')->insert([
            'id' => (string) Str::ulid(),
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'AUTONOME-'.Str::upper(Str::random(6)),
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('parcels')->insert([
            'id' => (string) Str::ulid(),
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'INVALID-'.Str::upper(Str::random(6)),
            'status' => 'sold',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('property rejects the removed land type', function () {
    $tenant = $this->testTenant('terra_foncier_property');

    $tenant->run(function (): void {
        expect(fn () => DB::table('properties')->insert([
            'id' => (string) Str::ulid(),
            'operation_id' => null,
            'reference' => 'PROPERTY-'.Str::upper(Str::random(10)),
            'name' => 'Invalid land property',
            'type' => 'land',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('terra boundary tables enforce PostGIS geometry validity', function () {
    $tenant = $this->testTenant('terra_foncier_postgis');

    $tenant->run(function (): void {
        $boundaryTables = collect([
            'lands',
            'subdivisions',
            'ilots',
            'parcels',
            'buildings',
        ])->filter(
            fn (string $table): bool => Schema::hasColumn($table, 'boundary')
        )->values();

        expect($boundaryTables->all())
            ->toEqual(['lands', 'subdivisions', 'ilots', 'parcels']);

        foreach ($boundaryTables as $table) {
            $constraint = DB::selectOne(
                <<<'SQL'
                    SELECT pg_get_constraintdef(con.oid) AS definition
                    FROM pg_constraint AS con
                    JOIN pg_class AS rel
                        ON rel.oid = con.conrelid
                    JOIN pg_namespace AS nsp
                        ON nsp.oid = rel.relnamespace
                    WHERE nsp.nspname = current_schema()
                      AND rel.relname = ?
                      AND con.conname = ?
                    SQL,
                [$table, $table.'_boundary_valid_check'],
            );

            expect($constraint)->not->toBeNull()
                ->and(strtolower($constraint->definition))->toContain('st_isvalid');
        }

        expect(fn () => DB::insert(
            <<<'SQL'
                INSERT INTO parcels (
                    id,
                    ilot_id,
                    reference,
                    parcel_number,
                    status,
                    boundary,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    NULL,
                    ?,
                    ?,
                    'provisional',
                    ST_Multi(
                        ST_GeomFromText(
                            'POLYGON((0 0, 1 1, 0 1, 1 0, 0 0))',
                            4326
                        )
                    ),
                    NOW(),
                    NOW()
                )
                SQL,
            [
                (string) Str::ulid(),
                'INVALID-GEOM-'.Str::upper(Str::random(8)),
                'INVALID-GEOM',
            ],
        ))->toThrow(QueryException::class);
    });
});

test('subdivision_lands enforces uniqueness and foreign keys', function () {
    $tenant = $this->testTenant('terra_foncier_subdivision_lands');

    $tenant->run(function (): void {
        $operationId = (string) Str::ulid();
        $subdivisionId = (string) Str::ulid();
        $landId = (string) Str::ulid();

        DB::table('operations')->insert([
            'id' => $operationId,
            'reference' => 'OP-'.Str::upper(Str::random(10)),
            'name' => 'Subdivision integrity test',
            'type' => 'land_development',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subdivisions')->insert([
            'id' => $subdivisionId,
            'operation_id' => $operationId,
            'reference' => 'SUB-'.Str::upper(Str::random(10)),
            'name' => 'Subdivision integrity test',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lands')->insert([
            'id' => $landId,
            'reference' => 'LAND-'.Str::upper(Str::random(10)),
            'name' => 'Land integrity test',
            'status' => 'acquired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subdivision_lands')->insert([
            'id' => (string) Str::ulid(),
            'subdivision_id' => $subdivisionId,
            'land_id' => $landId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('subdivision_lands')->insert([
            'id' => (string) Str::ulid(),
            'subdivision_id' => $subdivisionId,
            'land_id' => $landId,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        expect(fn () => DB::table('subdivision_lands')->insert([
            'id' => (string) Str::ulid(),
            'subdivision_id' => $subdivisionId,
            'land_id' => (string) Str::ulid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});
