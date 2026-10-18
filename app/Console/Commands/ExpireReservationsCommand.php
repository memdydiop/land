<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Commercial\ExpireReservation;
use App\Enums\ReservationStatus;
use App\Enums\TenantStatus;
use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExpireReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire les réservations échues des tenants actifs';

    public function handle(ExpireReservation $action): int
    {
        $cutoff = now();
        $expiredCount = 0;
        $failureCount = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use (
                $action,
                $cutoff,
                &$expiredCount,
                &$failureCount,
            ): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    try {
                        $tenant->run(function () use (
                            $tenant,
                            $action,
                            $cutoff,
                            &$expiredCount,
                            &$failureCount,
                        ): void {
                            Reservation::query()
                                ->where('status', ReservationStatus::Pending->value)
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<=', $cutoff)
                                ->orderBy('id')
                                ->chunkById(100, function ($reservations) use (
                                    $tenant,
                                    $action,
                                    &$expiredCount,
                                    &$failureCount,
                                ): void {
                                    foreach ($reservations as $reservation) {
                                        /** @var Reservation $reservation */
                                        try {
                                            $action->execute($reservation);
                                            $expiredCount++;
                                        } catch (Throwable $exception) {
                                            $failureCount++;

                                            Log::error(
                                                'Échec de l’expiration d’une réservation.',
                                                [
                                                    'tenant_id' => $tenant->getTenantKey(),
                                                    'reservation_id' => $reservation->getKey(),
                                                    'exception' => $exception,
                                                ],
                                            );

                                            $this->warn(
                                                "Échec réservation {$reservation->getKey()} "
                                                ."du tenant {$tenant->slug}.",
                                            );
                                        }
                                    }
                                });
                        });
                    } catch (Throwable $exception) {
                        $failureCount++;

                        Log::error(
                            'Échec du traitement des réservations d’un tenant.',
                            [
                                'tenant_id' => $tenant->getTenantKey(),
                                'tenant_slug' => $tenant->slug,
                                'exception' => $exception,
                            ],
                        );

                        $this->error(
                            "Échec du tenant {$tenant->slug}. "
                            .'Les autres tenants seront traités.',
                        );
                    }
                }
            });

        $this->info("Réservations expirées : {$expiredCount}.");
        $this->info("Échecs de traitement : {$failureCount}.");

        return $failureCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
