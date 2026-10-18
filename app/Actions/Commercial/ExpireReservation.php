<?php

declare(strict_types=1);

namespace App\Actions\Commercial;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExpireReservation
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
                    'reservation' => 'Seule une réservation en attente peut expirer.',
                ]);
            }

            if ($reservation->expires_at === null) {
                throw ValidationException::withMessages([
                    'reservation' => 'Cette réservation ne possède aucune échéance.',
                ]);
            }

            if ($reservation->expires_at->isFuture()) {
                throw ValidationException::withMessages([
                    'reservation' => 'La date d’expiration de la réservation n’est pas encore atteinte.',
                ]);
            }

            $reservation->status = ReservationStatus::Expired;
            $reservation->save();

            return $reservation->refresh();
        }, attempts: 3);
    }
}
