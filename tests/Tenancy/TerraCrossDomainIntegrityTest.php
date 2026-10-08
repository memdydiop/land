<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

$party = static function (string $name): string {
    $id = (string) Str::ulid();

    DB::table('parties')->insert([
        'id' => $id,
        'type' => 'person',
        'display_name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
};

$offer = static function (string $reference, string $currency = 'XOF'): string {
    $id = (string) Str::ulid();

    DB::table('commercial_offers')->insert([
        'id' => $id,
        'reference' => $reference,
        'title' => $reference,
        'price' => '10000000.0000',
        'currency' => $currency,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
};

$reservation = static function (
    string $offerId,
    string $partyId,
    string $status = 'pending',
    string $currency = 'XOF',
): string {
    $id = (string) Str::ulid();

    DB::table('reservations')->insert([
        'id' => $id,
        'commercial_offer_id' => $offerId,
        'party_id' => $partyId,
        'reference' => 'RES-'.Str::upper(Str::random(12)),
        'status' => $status,
        'reserved_at' => now(),
        'expires_at' => now()->addDays(7),
        'agreed_price' => '10000000.0000',
        'deposit_amount' => '500000.0000',
        'currency' => $currency,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
};

$parcel = static function (string $reference): string {
    $id = (string) Str::ulid();

    DB::table('parcels')->insert([
        'id' => $id,
        'ilot_id' => null,
        'reference' => $reference,
        'parcel_number' => $reference,
        'status' => 'provisional',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
};

$offerItem = static function (
    string $offerId,
    array $target,
): void {
    DB::table('commercial_offer_items')->insert([
        'id' => (string) Str::ulid(),
        'commercial_offer_id' => $offerId,
        'position' => 0,
        ...$target,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
};

test('reservation requires offer currency and a committed reservation requires at least one item', function () use ($party, $offer, $reservation) {
    $tenant = $this->testTenant('terra_5h_reservation_integrity');

    $tenant->run(function () use ($party, $offer, $reservation): void {
        $partyId = $party('5H Reservation Buyer');
        $offerId = $offer('5H-OFFER-CURRENCY');

        $reservation($offerId, $partyId, 'pending', 'XOF');

        expect(fn () => $reservation($offerId, $partyId, 'pending', 'EUR'))
            ->toThrow(QueryException::class);

        expect(fn () => $reservation($offerId, $partyId, 'confirmed', 'XOF'))
            ->toThrow(QueryException::class);
    });
});

test('confirmed reservation requires an active commercial offer', function () use ($party, $offer, $parcel, $offerItem, $reservation) {
    $tenant = $this->testTenant('terra_5h_inactive_offer');

    $tenant->run(function () use ($party, $offer, $parcel, $offerItem, $reservation): void {
        $partyId = $party('5H Inactive Offer Buyer');
        $offerId = $offer('5H-OFFER-INACTIVE');
        $parcelId = $parcel('5H-PARCEL-INACTIVE-OFFER');

        $offerItem($offerId, [
            'parcel_id' => $parcelId,
        ]);

        DB::table('commercial_offers')
            ->where('id', $offerId)
            ->update(['status' => 'withdrawn']);

        expect(fn () => $reservation($offerId, $partyId, 'confirmed', 'XOF'))
            ->toThrow(QueryException::class);
    });
});

test('only one committed reservation is allowed per commercial offer', function () use ($party, $offer, $parcel, $offerItem, $reservation) {
    $tenant = $this->testTenant('terra_5h_offer_unique');

    $tenant->run(function () use ($party, $offer, $parcel, $offerItem, $reservation): void {
        $buyerId = $party('5H Unique Buyer');
        $offerId = $offer('5H-OFFER-UNIQUE');
        $parcelId = $parcel('5H-PARCEL-UNIQUE');
        $offerItem($offerId, ['parcel_id' => $parcelId]);

        $reservationOne = $reservation($offerId, $buyerId);
        $reservationTwo = $reservation($offerId, $buyerId);

        DB::table('reservations')->where('id', $reservationOne)->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $reservationTwo)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('property and its unit cannot be committed through different offers', function () use ($party, $offer, $reservation, $parcel, $offerItem) {
    $tenant = $this->testTenant('terra_5h_property_unit_conflict');

    $tenant->run(function () use ($party, $offer, $reservation, $parcel, $offerItem): void {
        $buyerOne = $party('5H Property Buyer');
        $buyerTwo = $party('5H Unit Buyer');
        $propertyId = (string) Str::ulid();
        $buildingId = (string) Str::ulid();
        $unitId = (string) Str::ulid();
        $propertyParcelId = $parcel('5H-PARCEL-PROPERTY');

        DB::table('properties')->insert([
            'id' => $propertyId,
            'operation_id' => null,
            'reference' => '5H-PROPERTY',
            'name' => '5H Property',
            'type' => 'residential',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_parcels')->insert([
            'id' => (string) Str::ulid(),
            'property_id' => $propertyId,
            'parcel_id' => $propertyParcelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buildings')->insert([
            'id' => $buildingId,
            'property_id' => $propertyId,
            'name' => '5H Building',
            'reference' => '5H-BUILDING',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('units')->insert([
            'id' => $unitId,
            'building_id' => $buildingId,
            'reference' => '5H-UNIT',
            'unit_type' => 'apartment',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $propertyOffer = $offer('5H-OFFER-PROPERTY');
        $unitOffer = $offer('5H-OFFER-UNIT');
        $offerItem($propertyOffer, ['property_id' => $propertyId]);
        $offerItem($unitOffer, ['unit_id' => $unitId]);

        $propertyReservation = $reservation($propertyOffer, $buyerOne);
        $unitReservation = $reservation($unitOffer, $buyerTwo);

        DB::table('reservations')->where('id', $propertyReservation)->update(['status' => 'confirmed']);

        expect(fn () => DB::table('reservations')
            ->where('id', $unitReservation)
            ->update(['status' => 'confirmed'])
        )->toThrow(QueryException::class);
    });
});

test('sibling units of the same property remain independently reservable', function () use ($party, $offer, $reservation, $parcel, $offerItem) {
    $tenant = $this->testTenant('terra_5h_sibling_units');

    $tenant->run(function () use ($party, $offer, $reservation, $offerItem): void {
        $buyerOne = $party('5H Unit One Buyer');
        $buyerTwo = $party('5H Unit Two Buyer');
        $propertyId = (string) Str::ulid();
        $buildingId = (string) Str::ulid();
        $unitOneId = (string) Str::ulid();
        $unitTwoId = (string) Str::ulid();

        DB::table('properties')->insert([
            'id' => $propertyId,
            'operation_id' => null,
            'reference' => '5H-SIBLING-PROPERTY',
            'name' => '5H Sibling Property',
            'type' => 'residential',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buildings')->insert([
            'id' => $buildingId,
            'property_id' => $propertyId,
            'name' => '5H Sibling Building',
            'reference' => '5H-SIBLING-BUILDING',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$unitOneId, '5H-UNIT-1'], [$unitTwoId, '5H-UNIT-2']] as [$unitId, $reference]) {
            DB::table('units')->insert([
                'id' => $unitId,
                'building_id' => $buildingId,
                'reference' => $reference,
                'unit_type' => 'apartment',
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $offerOne = $offer('5H-OFFER-UNIT-1');
        $offerTwo = $offer('5H-OFFER-UNIT-2');
        $offerItem($offerOne, ['unit_id' => $unitOneId]);
        $offerItem($offerTwo, ['unit_id' => $unitTwoId]);

        $reservationOne = $reservation($offerOne, $buyerOne);
        $reservationTwo = $reservation($offerTwo, $buyerTwo);

        DB::table('reservations')->where('id', $reservationOne)->update(['status' => 'confirmed']);
        DB::table('reservations')->where('id', $reservationTwo)->update(['status' => 'confirmed']);

        expect(
            DB::table('reservations')->whereIn('id', [$reservationOne, $reservationTwo])->where('status', 'confirmed')->count()
        )->toBe(2);
    });
});

test('offer relation models expose commercial offer items consistently', function () {
    $tenant = $this->testTenant('terra_5h_model_relations');

    $tenant->run(function (): void {
        expect(Schema::hasColumn('parcels', 'lot_number'))->toBeTrue()
            ->and(Schema::hasColumn('parcels', 'cadastral_reference'))->toBeTrue();

        expect((new \App\Models\Land)->commercialOfferItems()->getModel())
            ->toBeInstanceOf(\App\Models\CommercialOfferItem::class);
        expect((new \App\Models\Parcel)->commercialOfferItems()->getModel())
            ->toBeInstanceOf(\App\Models\CommercialOfferItem::class);
        expect((new \App\Models\Property)->commercialOfferItems()->getModel())
            ->toBeInstanceOf(\App\Models\CommercialOfferItem::class);
        expect((new \App\Models\Unit)->commercialOfferItems()->getModel())
            ->toBeInstanceOf(\App\Models\CommercialOfferItem::class);
    });
});
