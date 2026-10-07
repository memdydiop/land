<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('commercial offer supports a primary buyer and co-buyers', function () {
    $tenant = $this->testTenant('terra_offer_buyers');

    $tenant->run(function (): void {
        $buyerId = (string) Str::ulid();
        $coBuyerId = (string) Str::ulid();
        $offerId = (string) Str::ulid();

        foreach ([
            [$buyerId, 'Primary Buyer'],
            [$coBuyerId, 'Co Buyer'],
        ] as [$partyId, $displayName]) {
            DB::table('parties')->insert([
                'id' => $partyId,
                'type' => 'person',
                'display_name' => $displayName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'buyer_party_id' => $buyerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Offer buyers test',
            'price' => 20000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('offer_co_buyers')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'party_id' => $coBuyerId,
            'share' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::table('commercial_offers')
                ->where('id', $offerId)
                ->value('buyer_party_id')
        )->toBe($buyerId);

        expect(
            DB::table('offer_co_buyers')
                ->where('commercial_offer_id', $offerId)
                ->count()
        )->toBe(1);
    });
});

test('a primary buyer cannot also be a co-buyer', function () {
    $tenant = $this->testTenant('terra_offer_buyer_conflict');

    $tenant->run(function (): void {
        $buyerId = (string) Str::ulid();
        $offerId = (string) Str::ulid();

        DB::table('parties')->insert([
            'id' => $buyerId,
            'type' => 'person',
            'display_name' => 'Duplicate Buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'buyer_party_id' => $buyerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Buyer conflict test',
            'price' => 10000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('offer_co_buyers')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'party_id' => $buyerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('reservation principal party must match the commercial offer buyer', function () {
    $tenant = $this->testTenant('terra_offer_reservation_buyer');

    $tenant->run(function (): void {
        $buyerId = (string) Str::ulid();
        $otherPartyId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();

        foreach ([
            [$buyerId, 'Offer Buyer'],
            [$otherPartyId, 'Other Party'],
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
            'parcel_number' => 'BUYER-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'buyer_party_id' => $buyerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Reservation buyer test',
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

        expect(fn () => DB::table('reservations')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'party_id' => $otherPartyId,
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'reserved_at' => now(),
            'agreed_price' => 15000000,
            'deposit_amount' => 500000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('confirmed reservation freezes offer buyers', function () {
    $tenant = $this->testTenant('terra_offer_buyers_frozen');

    $tenant->run(function (): void {
        $buyerId = (string) Str::ulid();
        $coBuyerId = (string) Str::ulid();
        $parcelId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationId = (string) Str::ulid();

        foreach ([
            [$buyerId, 'Frozen Buyer'],
            [$coBuyerId, 'Frozen Co Buyer'],
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
            'parcel_number' => 'FROZEN-'.$parcelId,
            'status' => 'provisional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'buyer_party_id' => $buyerId,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Frozen buyers test',
            'price' => 18000000,
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

        DB::table('reservations')->insert([
            'id' => $reservationId,
            'commercial_offer_id' => $offerId,
            'party_id' => $buyerId,
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'confirmed',
            'reserved_at' => now(),
            'agreed_price' => 18000000,
            'deposit_amount' => 900000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('commercial_offers')
            ->where('id', $offerId)
            ->update([
                'buyer_party_id' => $coBuyerId,
            ])
        )->toThrow(QueryException::class);

        expect(fn () => DB::table('offer_co_buyers')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $offerId,
            'party_id' => $coBuyerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

test('offer buyer schema is available', function () {
    $tenant = $this->testTenant('terra_offer_buyer_schema');

    $tenant->run(function (): void {
        expect(Schema::hasColumn('commercial_offers', 'buyer_party_id'))->toBeTrue()
            ->and(Schema::hasTable('offer_co_buyers'))->toBeTrue()
            ->and(Schema::hasColumn('offer_co_buyers', 'share'))->toBeTrue();
    });
});

test('primary buyer cannot conflict with an existing active reservation', function () {
    $tenant = $this->testTenant('terra_offer_buyer_reassignment_v2');

    $tenant->run(function (): void {
        $reservationPartyId = (string) Str::ulid();
        $newBuyerId = (string) Str::ulid();
        $offerId = (string) Str::ulid();
        $reservationId = (string) Str::ulid();

        foreach ([
            [$reservationPartyId, 'Reservation Party'],
            [$newBuyerId, 'New Buyer'],
        ] as [$partyId, $displayName]) {
            DB::table('parties')->insert([
                'id' => $partyId,
                'type' => 'person',
                'display_name' => $displayName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('commercial_offers')->insert([
            'id' => $offerId,
            'buyer_party_id' => null,
            'reference' => 'OFFER-'.Str::upper(Str::random(10)),
            'title' => 'Buyer reassignment test',
            'price' => 12000000,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reservations')->insert([
            'id' => $reservationId,
            'commercial_offer_id' => $offerId,
            'party_id' => $reservationPartyId,
            'reference' => 'RES-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'reserved_at' => now(),
            'agreed_price' => 12000000,
            'deposit_amount' => 600000,
            'currency' => 'XOF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('commercial_offers')
            ->where('id', $offerId)
            ->update([
                'buyer_party_id' => $newBuyerId,
            ])
        )->toThrow(QueryException::class);
    });
});
