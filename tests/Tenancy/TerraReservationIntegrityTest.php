<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('multiple pending reservations can coexist while only one can be confirmed', function () {
    $tenant = $this->testTenant('terra_reservation_same_offer');

    $tenant->run(function (): void {
        $partyOneId = (string) Str::ulid();
        $partyTwoId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationOneId = (string) Str::ulid();
        $reservationTwoId = (string) Str::ulid();

        foreach ([
            [$partyOneId, 'Buyer One'],
            [$partyTwoId, 'Buyer Two'],
        ] as [$partyId, $displayName]) {
            DB::table('parties')->insert([
                'id' => $partyId,
                'type' => 'person',
                'display_name' => $displayName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'RES-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Reservation concurrency test',
            'price' => 20000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'parcel_id' => $parcelId,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$reservationOneId, $partyOneId],
            [$reservationTwoId, $partyTwoId],
        ] as [$reservationId, $partyId]) {
            DB::table('reservations')->insert([
                'id' => $reservationId,
                'commercial_offer_id' => $offerId,
                'party_id' => $partyId,
                'reference' => 'RES-'.Str::upper(Str::random(10)),
                'status' => 'pending',
                'reserved_at' => now(),
                'expires_at' => now()->addDays(7),
                'agreed_price' => 20000000,
                'deposit_amount' => 1000000,
                'deposit_due_date' => now()->addDays(3)->toDateString(),
                'currency' => 'XOF',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        expect(
            DB::table('reservations')
                ->where('commercial_offer_id', $offerId)
                ->where('status', 'pending')
                ->count()
        )->toBe(2);

        DB::table('reservations')
            ->where("id", $reservationOneId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ]);

        expect(fn () => DB::table('reservations')
            ->where("id", $reservationTwoId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);
    });
});

test('confirmed reservations on different offers cannot share the same parcel', function () {
    $tenant = $this->testTenant('terra_reservation_cross_offer');

    $tenant->run(function (): void {
        $partyOneId = (string) Str::ulid();
        $partyTwoId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerOneId = (string) Str::ulid();
        $offerTwoId = (string) Str::ulid();
        $reservationOneId = (string) Str::ulid();
        $reservationTwoId = (string) Str::ulid();

        foreach ([
            [$partyOneId, 'Buyer One'],
            [$partyTwoId, 'Buyer Two'],
        ] as [$partyId, $displayName]) {
            DB::table('parties')->insert([
                'id' => $partyId,
                'type' => 'person',
                'display_name' => $displayName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'CROSS-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$offerOneId, 'Offer One'],
            [$offerTwoId, 'Offer Two'],
        ] as [$offerId, $title]) {
            DB::table('commercial_offers')->insert([
                'id' => $offerId,
                'reference' => 'OFFER-'.Str::upper(Str::random(10)),
                'title' => $title,
                'price' => 15000000,
                'currency' => 'XOF',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('commercial_offer_items')->insert([
                'id' => (string) Str::ulid(),
                'commercial_offer_id' => $offerId,
                'parcel_id' => $parcelId,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            [$reservationOneId, $partyOneId, $offerOneId],
            [$reservationTwoId, $partyTwoId, $offerTwoId],
        ] as [$reservationId, $partyId, $offerId]) {
            DB::table('reservations')->insert([
                'id' => $reservationId,
                'commercial_offer_id' => $offerId,
                'party_id' => $partyId,
                'reference' => 'RES-'.Str::upper(Str::random(10)),
                'status' => 'pending',
                'reserved_at' => now(),
                'agreed_price' => 15000000,
                'deposit_amount' => 750000,
                'deposit_due_date' => now()->addDays(3)->toDateString(),
                'currency' => 'XOF',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('reservations')
            ->where("id", $reservationOneId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ]);

        expect(fn () => DB::table('reservations')
            ->where("id", $reservationTwoId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ])
        )->toThrow(QueryException::class);
    });
});

test('reservation financial fields enforce deposit not greater than agreed price', function () {
    $tenant = $this->testTenant('terra_reservation_amounts');

    $tenant->run(function (): void {
        $partyId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $partyId,
            'type' => 'person',
            'display_name' => 'Reservation Amount Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('parcels')->insert([
            'id' => $parcelId,
            'ilot_id' => null,
            'reference' => 'PARCEL-'.Str::upper(Str::random(10)),
            'parcel_number' => 'AMOUNT-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Reservation amounts test',
            'price' => 10000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'parcel_id' => $parcelId,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('reservations')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'party_id' => $partyId,
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'reserved_at' => now(),
            'agreed_price' => 10000000,
            'deposit_amount' => 11000000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('reservation schema uses explicit agreed price and deposit fields', function () {
    $tenant = $this->testTenant('terra_reservation_schema');

    $tenant->run(function (): void {
        expect(Schema::hasColumn('reservations', 'agreed_price'))->toBeTrue()
            ->and(Schema::hasColumn('reservations', 'deposit_amount'))->toBeTrue()
            ->and(Schema::hasColumn('reservations', 'deposit_due_date'))->toBeTrue()
            ->and(Schema::hasColumn('reservations', 'amount'))->toBeFalse();
    });
});
