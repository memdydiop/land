<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('commercial offers can contain multiple explicit targets', function () {
    $tenant = $this->testTenant('terra_commercial_offer_items');

    $tenant->run(function (): void {
        $parcelId = (string) Str::ulid();
        $propertyId = (string) Str::ulid();
        $offerId = (string) Str::ulid();

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'P-'.Str::upper(Str::random(6)),
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('properties')->insert([
            'id' => $propertyId,
            'operation_id' => null,
            'reference' => 'PROPERTY-'.Str::upper(Str::random(10)),
            'name' => 'Villa bundle test',
            'type' => 'residential',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Villa + Parcel',
            'price' => 25000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'land_id' => null,
            'parcel_id' => $parcelId,
            'property_id' => null,
            'unit_id' => null,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'land_id' => null,
            'parcel_id' => null,
            'property_id' => $propertyId,
            'unit_id' => null,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('commercial_offer_items')
                ->where('commercial_offer_id', $offerId)
                ->count()
        )->toBe(2);
    });
});

test('commercial offer item requires exactly one target', function () {
    $tenant = $this->testTenant('terra_commercial_offer_xor');

    $tenant->run(function (): void {
        $offerId = (string) Str::ulid();

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'XOR test',
            'price' => 1000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'land_id' => null,
            'parcel_id' => null,
            'property_id' => null,
            'unit_id' => null,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        expect(fn () => DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'land_id' => (string) Str::ulid(),
            'parcel_id' => (string) Str::ulid(),
            'property_id' => null,
            'unit_id' => null,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('commercial offer no longer exposes legacy target columns or commercial statuses', function () {
    $tenant = $this->testTenant('terra_commercial_offer_schema');

    $tenant->run(function (): void {
        expect(Schema::hasColumn('commercial_offers', 'parcel_id'))->toBeFalse()
            ->and(Schema::hasColumn('commercial_offers', 'unit_id'))->toBeFalse();

        expect(fn () => DB::table('commercial_offers')->insert([
            'id' => (string) Str::ulid(),
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Invalid status',
            'price' => 1000000,
            'currency' => 'XOF',
            'status' => 'sold',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('commercial offer migration preserves a legacy parcel target as an item', function () {
    $suffix = 'terra_commercial_offer_migration_'.Str::lower(Str::random(8));
    $tenant = $this->testTenant($suffix);

    $tenant->run(function (): void {
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'LEGACY-PARCEL-'.Str::upper(Str::random(8)),
            'parcel_number' => 'LEGACY-'.Str::upper(Str::random(6)),
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('commercial_offers', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->ulid('parcel_id')->nullable();
            $table->ulid('unit_id')->nullable();

            $table->foreign('parcel_id')
                ->references('id')
                ->on('parcels')
                ->restrictOnDelete();

            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();

            $table->index('parcel_id');
            $table->index('unit_id');
            $table->index(['status', 'parcel_id']);
            $table->index(['status', 'unit_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_target_check
            CHECK (
                (parcel_id IS NOT NULL AND unit_id IS NULL)
                OR
                (parcel_id IS NULL AND unit_id IS NOT NULL)
            )
            SQL);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'LEGACY-OFFER-'.Str::upper(Str::random(8)),
            'title' => 'Legacy parcel offer',
            'parcel_id' => $parcelId,
            'unit_id' => null,
            'price' => 15000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/tenant/2026_10_07_100003_align_commercial_offers_with_items.php'
        );

        $migration->up();

        $item = DB::table('commercial_offer_items')
            ->where('commercial_offer_id', $offerId)
            ->first();

        expect($item)->not->toBeNull()
            ->and($item->parcel_id)->toBe($parcelId)
            ->and($item->land_id)->toBeNull()
            ->and($item->property_id)->toBeNull()
            ->and($item->unit_id)->toBeNull()
            ->and($item->position)->toBe(0)
            ->and(Schema::hasColumn('commercial_offers', 'parcel_id'))->toBeFalse()
            ->and(Schema::hasColumn('commercial_offers', 'unit_id'))->toBeFalse();
    });
});
