<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Enums\CommercialOfferStatus;
use App\Enums\ReservationStatus;
use App\Models\CommercialOffer;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmReservation
{
    public function execute(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = Reservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== ReservationStatus::Pending) {
                throw ValidationException::withMessages([
                    'reservation' => 'Seule une réservation en attente peut être confirmée.',
                ]);
            }

            if (
                $reservation->expires_at !== null
                && $reservation->expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'reservation' => 'La réservation a expiré.',
                ]);
            }

            $offer = CommercialOffer::query()
                ->whereKey($reservation->commercial_offer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($offer->status !== CommercialOfferStatus::Active) {
                throw ValidationException::withMessages([
                    'commercial_offer' => 'Seule une offre active peut être confirmée.',
                ]);
            }

            $today = now()->toDateString();

            if (
                $offer->valid_from !== null
                && $offer->valid_from->toDateString() > $today
            ) {
                throw ValidationException::withMessages([
                    'commercial_offer' => 'La période de validité de l’offre n’a pas commencé.',
                ]);
            }

            if (
                $offer->valid_until !== null
                && $offer->valid_until->toDateString() < $today
            ) {
                throw ValidationException::withMessages([
                    'commercial_offer' => 'L’offre a dépassé sa date de validité.',
                ]);
            }

            if (! $offer->items()->exists()) {
                throw ValidationException::withMessages([
                    'commercial_offer' => 'Une offre doit contenir au moins un élément commercialisable.',
                ]);
            }

            $reservation->status = ReservationStatus::Confirmed;
            $reservation->save();

            return $reservation->refresh();
        }, attempts: 3);
    }
}
