<?php

declare(strict_types=1);

use App\Actions\Commercial\ConfirmReservation;
use App\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TenancyTestCase;
use Illuminate\Database\QueryException;

uses(TenancyTestCase::class);

function confirmReservationParty(string $name): string
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

function confirmReservationParcel(string $reference): string
{
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
}

function confirmReservationOffer(
    string $reference,
    string $status = 'active',
    ?string $validFrom = null,
    ?string $validUntil = null,
    bool $withItem = true,
): string {
    $id = (string) Str::ulid();

    $parcelId = $withItem
        ? confirmReservationParcel('PAR-'.Str::upper(Str::random(10)))
        : null;

    DB::table('commercial_offers')->insert([
        'id' => $id,
        'reference' => $reference.'-'.Str::upper(Str::random(8)),
        'title' => $reference,
        'price' => '10000000.0000',
        'currency' => 'XOF',
        'status' => $status,
        'valid_from' => $validFrom,
        'valid_until' => $validUntil,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($withItem) {
        DB::table('commercial_offer_items')->insert([
            'id' => (string) Str::ulid(),
            'commercial_offer_id' => $id,
            'land_id' => null,
            'parcel_id' => $parcelId,
            'property_id' => null,
            'unit_id' => null,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}

function confirmReservationRecord(
    string $offerId,
    string $partyId,
    string $status = 'pending',
    ?\DateTimeInterface $expiresAt = null,
): string {
    $id = (string) Str::ulid();

    DB::table('reservations')->insert([
        'id' => $id,
        'commercial_offer_id' => $offerId,
        'party_id' => $partyId,
        'reference' => 'RES-'.Str::upper(Str::random(12)),
        'status' => $status,
        'reserved_at' => now()->subMinute(),
        'expires_at' => $expiresAt?->toDateTimeString() ?? now()->addDays(7),
        'agreed_price' => '10000000.0000',
        'deposit_amount' => '500000.0000',
        'currency' => 'XOF',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

test('it confirms a valid pending reservation', function (): void {
    $tenant = $this->testTenant('confirm_reservation_success');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer('OFFER-SUCCESS');
        $reservationId = confirmReservationRecord($offerId, $partyId);

        $reservation = app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        );

        expect($reservation->status)->toBe(ReservationStatus::Confirmed)
            ->and(
                DB::table('reservations')
                    ->where('id', $reservationId)
                    ->value('status'),
            )->toBe('confirmed');
    });
});

test('it rejects a reservation that is not pending', function (): void {
    $tenant = $this->testTenant('confirm_reservation_not_pending');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer('OFFER-NOT-PENDING');
        $reservationId = confirmReservationRecord(
            $offerId,
            $partyId,
            'cancelled',
        );

        expect(fn () => app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('cancelled');
    });
});

test('it rejects an expired reservation without changing its status', function (): void {
    $tenant = $this->testTenant('confirm_reservation_expired');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer('OFFER-EXPIRED-RESERVATION');
        $reservationId = confirmReservationRecord(
            $offerId,
            $partyId,
            'pending',
            now()->subMinute(),
        );

        expect(fn () => app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects an inactive offer without changing the reservation', function (): void {
    $tenant = $this->testTenant('confirm_reservation_inactive_offer');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer(
            'OFFER-INACTIVE',
            status: 'withdrawn',
        );
        $reservationId = confirmReservationRecord($offerId, $partyId);

        expect(fn () => app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects an offer whose validity has not started', function (): void {
    $tenant = $this->testTenant('confirm_reservation_future_offer');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer(
            'OFFER-FUTURE',
            validFrom: now()->addDay()->toDateString(),
        );
        $reservationId = confirmReservationRecord($offerId, $partyId);

        expect(fn () => app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects an expired offer', function (): void {
    $tenant = $this->testTenant('confirm_reservation_past_offer');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer(
            'OFFER-PAST',
            validUntil: now()->subDay()->toDateString(),
        );
        $reservationId = confirmReservationRecord($offerId, $partyId);

        expect(fn () => app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects an offer without items', function (): void {
    $tenant = $this->testTenant('confirm_reservation_empty_offer');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Reservation buyer');
        $offerId = confirmReservationOffer(
            'OFFER-EMPTY',
            withItem: false,
        );
        $reservationId = confirmReservationRecord($offerId, $partyId);

        expect(fn () => app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects confirmation when another confirmed reservation holds the same parcel', function (): void {
    $tenant = $this->testTenant('confirm_reservation_parcel_conflict');

    $tenant->run(function (): void {
        $buyerOne = confirmReservationParty('First reservation buyer');
        $buyerTwo = confirmReservationParty('Second reservation buyer');

        $offerOne = confirmReservationOffer('OFFER-FIRST');
        $offerTwo = confirmReservationOffer('OFFER-SECOND');

        $parcelId = DB::table('commercial_offer_items')
            ->where('commercial_offer_id', $offerOne)
            ->value('parcel_id');

        expect($parcelId)->not->toBeNull();

        // Les deux offres ciblent désormais la même parcelle.
        DB::table('commercial_offer_items')
            ->where('commercial_offer_id', $offerTwo)
            ->update([
                'parcel_id' => $parcelId,
                'updated_at' => now(),
            ]);

        $reservationOne = confirmReservationRecord($offerOne, $buyerOne);
        $reservationTwo = confirmReservationRecord($offerTwo, $buyerTwo);

        app(ConfirmReservation::class)->execute(
            \App\Models\Reservation::query()->findOrFail($reservationOne),
        );

        $exception = null;

        try {
            app(ConfirmReservation::class)->execute(
                \App\Models\Reservation::query()->findOrFail($reservationTwo),
            );
        } catch (QueryException $caught) {
            $exception = $caught;
        }

        expect($exception)->toBeInstanceOf(QueryException::class)
            ->and($exception->errorInfo[0] ?? null)->toBe('23505')
            ->and($exception->getMessage())->toContain(
                'A committed reservation already conflicts with target',
            );

        expect(DB::table('reservations')
            ->where('id', $reservationTwo)
            ->value('status'))->toBe('pending');
    });
});
