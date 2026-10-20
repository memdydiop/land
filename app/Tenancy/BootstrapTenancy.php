<?php

declare(strict_types=1);

namespace App\Tenancy;

use Stancl\Tenancy\Events;
use Throwable;

final class BootstrapTenancy
{
    public function handle(Events\TenancyInitialized $event): void
    {
        $tenancy = $event->tenancy;
        $startedBootstrappers = [];

        try {
            event(new Events\BootstrappingTenancy($tenancy));

            foreach ($tenancy->getBootstrappers() as $bootstrapper) {
                // Le bootstrapper peut modifier l'état avant de lever une exception.
                $startedBootstrappers[] = $bootstrapper;

                $bootstrapper->bootstrap($tenancy->tenant);
            }

            event(new Events\TenancyBootstrapped($tenancy));
        } catch (Throwable $exception) {
            try {
                event(new Events\RevertingToCentralContext($tenancy));
            } catch (Throwable $rollbackException) {
                report($rollbackException);
            }

            foreach (array_reverse($startedBootstrappers) as $bootstrapper) {
                try {
                    $bootstrapper->revert();
                } catch (Throwable $rollbackException) {
                    report($rollbackException);
                }
            }

            try {
                event(new Events\RevertedToCentralContext($tenancy));
            } catch (Throwable $rollbackException) {
                report($rollbackException);
            }

            // Ne jamais laisser un contexte tenant actif après un bootstrap échoué.
            $tenancy->initialized = false;
            $tenancy->tenant = null;

            // Préserver l'exception qui a déclenché l'échec initial.
            throw $exception;
        }
    }
}
