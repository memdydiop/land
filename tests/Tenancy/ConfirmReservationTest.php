<?php

declare(strict_types=1);

use App\Actions\Commercial\ConfirmReservation;
use App\Actions\Commercial\ExpireReservation;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TenancyTestCase;

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
    ?DateTimeInterface $expiresAt = null,
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationId),
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
            Reservation::query()->findOrFail($reservationOne),
        );

        $exception = null;

        try {
            app(ConfirmReservation::class)->execute(
                Reservation::query()->findOrFail($reservationTwo),
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

test('it expires a pending reservation whose deadline has passed', function (): void {
    $tenant = $this->testTenant('expire_reservation_success');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Expiration buyer');
        $offerId = confirmReservationOffer('OFFER-EXPIRE-SUCCESS');

        $reservationId = confirmReservationRecord(
            $offerId,
            $partyId,
            'pending',
            now()->subSecond(),
        );

        $reservation = app(ExpireReservation::class)->execute(
            Reservation::query()->findOrFail($reservationId),
        );

        expect($reservation->status)->toBe(ReservationStatus::Expired)
            ->and(
                DB::table('reservations')
                    ->where('id', $reservationId)
                    ->value('status'),
            )->toBe('expired');
    });
});

test('it rejects expiration before the deadline without changing the reservation', function (): void {
    $tenant = $this->testTenant('expire_reservation_future');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Expiration buyer');
        $offerId = confirmReservationOffer('OFFER-EXPIRE-FUTURE');

        $reservationId = confirmReservationRecord(
            $offerId,
            $partyId,
            'pending',
            now()->addDay(),
        );

        expect(fn () => app(ExpireReservation::class)->execute(
            Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects expiration when no deadline is defined', function (): void {
    $tenant = $this->testTenant('expire_reservation_no_deadline');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Expiration buyer');
        $offerId = confirmReservationOffer('OFFER-EXPIRE-NO-DEADLINE');

        $reservationId = confirmReservationRecord($offerId, $partyId);

        DB::table('reservations')
            ->where('id', $reservationId)
            ->update(['expires_at' => null]);

        expect(fn () => app(ExpireReservation::class)->execute(
            Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('pending');
    });
});

test('it rejects expiration of a reservation that is no longer pending', function (): void {
    $tenant = $this->testTenant('expire_reservation_cancelled');

    $tenant->run(function (): void {
        $partyId = confirmReservationParty('Expiration buyer');
        $offerId = confirmReservationOffer('OFFER-EXPIRE-CANCELLED');

        $reservationId = confirmReservationRecord(
            $offerId,
            $partyId,
            'cancelled',
            now()->subSecond(),
        );

        expect(fn () => app(ExpireReservation::class)->execute(
            Reservation::query()->findOrFail($reservationId),
        ))->toThrow(ValidationException::class);

        expect(DB::table('reservations')->where('id', $reservationId)->value('status'))
            ->toBe('cancelled');
    });
});

test('expiration command expires due reservations and preserves future reservations', function (): void {
    $tenant = $this->testTenant('expire_command_active');

    $ids = $tenant->run(function (): array {
        $partyId = confirmReservationParty('Command expiration buyer');
        $offerId = confirmReservationOffer('COMMAND-EXPIRATION');

        return [
            'expired' => confirmReservationRecord(
                $offerId,
                $partyId,
                'pending',
                now()->subMinute(),
            ),
            'future' => confirmReservationRecord(
                $offerId,
                $partyId,
                'pending',
                now()->addDay(),
            ),
        ];
    });

    $exitCode = Artisan::call('reservations:expire');

    $statuses = $tenant->run(
        fn () => DB::table('reservations')
            ->whereIn('id', array_values($ids))
            ->pluck('status', 'id')
            ->all(),
    );

    expect($exitCode)->toBe(0)
        ->and($statuses[$ids['expired']])->toBe('expired')
        ->and($statuses[$ids['future']])->toBe('pending')
        ->and(tenancy()->initialized)->toBeFalse()
        ->and(tenant())->toBeNull();
});

test('expiration command ignores suspended tenants', function (): void {
    $tenant = $this->testTenant('expire_command_suspended');

    $tenant->forceFill(['status' => 'suspended'])->save();

    $reservationId = $tenant->run(function (): string {
        $partyId = confirmReservationParty('Suspended tenant buyer');
        $offerId = confirmReservationOffer('SUSPENDED-EXPIRATION');

        return confirmReservationRecord(
            $offerId,
            $partyId,
            'pending',
            now()->subMinute(),
        );
    });

    $exitCode = Artisan::call('reservations:expire');

    $status = $tenant->run(
        fn () => DB::table('reservations')
            ->where('id', $reservationId)
            ->value('status'),
    );

    expect($exitCode)->toBe(0)
        ->and($status)->toBe('pending')
        ->and(tenancy()->initialized)->toBeFalse()
        ->and(tenant())->toBeNull();
});
