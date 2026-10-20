<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\BootstrapTenancy;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Stancl\Tenancy\Events;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

/**
 * Exécute un scénario avec un dispatcher d'événements isolé.
 * Restaure l'état du singleton tenancy et le dispatcher après le test.
 */
function runIsolatedTenantBootstrapScenario(callable $callback): mixed
{
    $app = app();
    $tenancy = tenancy();

    $originalTenant = $tenancy->tenant;
    $originalInitialized = $tenancy->initialized;
    $originalBootstrappersResolver = $tenancy->getBootstrappersUsing;
    $originalDispatcher = $app->make('events');

    $dispatcher = new Dispatcher($app);

    $app->instance('events', $dispatcher);
    Facade::clearResolvedInstance('events');

    $tenancy->tenant = new Tenant;
    $tenancy->initialized = true;

    try {
        return $callback($tenancy, $dispatcher);
    } finally {
        $tenancy->tenant = $originalTenant;
        $tenancy->initialized = $originalInitialized;
        $tenancy->getBootstrappersUsing = $originalBootstrappersResolver;

        $app->instance('events', $originalDispatcher);
        Facade::clearResolvedInstance('events');
    }
}

test('failed BootstrappingTenancy event restores the tenancy state', function () {
    runIsolatedTenantBootstrapScenario(
        function ($tenancy, Dispatcher $dispatcher): void {
            $failure = new RuntimeException(
                'Simulated failure before tenant bootstrappers.'
            );

            $dispatcher->listen(
                Events\BootstrappingTenancy::class,
                static function () use ($failure): never {
                    throw $failure;
                },
            );

            $restorationEventDispatched = false;

            $dispatcher->listen(
                Events\RevertedToCentralContext::class,
                static function () use (&$restorationEventDispatched): void {
                    $restorationEventDispatched = true;
                },
            );

            try {
                expect(
                    fn () => app(BootstrapTenancy::class)->handle(
                        new Events\TenancyInitialized($tenancy),
                    ),
                )->toThrow(
                    RuntimeException::class,
                    'Simulated failure before tenant bootstrappers.',
                );

                expect($tenancy->initialized)->toBeFalse()
                    ->and($tenancy->tenant)->toBeNull()
                    ->and($restorationEventDispatched)->toBeTrue();
            } finally {
                // Prevent a failed assertion from contaminating the test process.
                $tenancy->initialized = false;
                $tenancy->tenant = null;
            }
        },
    );
});

test('failed bootstrapper and completed bootstrappers are reverted', function () {
    runIsolatedTenantBootstrapScenario(
        function ($tenancy, Dispatcher $dispatcher): void {
            $first = new class
            {
                public int $bootstrapCalls = 0;

                public int $revertCalls = 0;

                public function bootstrap($tenant): void
                {
                    $this->bootstrapCalls++;
                }

                public function revert(): void
                {
                    $this->revertCalls++;
                }
            };

            $second = new class
            {
                public int $bootstrapCalls = 0;

                public int $revertCalls = 0;

                public function bootstrap($tenant): void
                {
                    $this->bootstrapCalls++;

                    throw new RuntimeException(
                        'Simulated second bootstrapper failure.'
                    );
                }

                public function revert(): void
                {
                    $this->revertCalls++;
                }
            };

            app()->instance($first::class, $first);
            app()->instance($second::class, $second);

            $tenancy->getBootstrappersUsing = static fn ($tenant): array => [
                $first::class,
                $second::class,
            ];

            $restorationEventDispatched = false;

            $dispatcher->listen(
                Events\RevertedToCentralContext::class,
                static function () use (&$restorationEventDispatched): void {
                    $restorationEventDispatched = true;
                },
            );

            try {
                expect(
                    fn () => app(BootstrapTenancy::class)->handle(
                        new Events\TenancyInitialized($tenancy),
                    ),
                )->toThrow(
                    RuntimeException::class,
                    'Simulated second bootstrapper failure.',
                );

                expect($first->bootstrapCalls)->toBe(1)
                    ->and($first->revertCalls)->toBe(1)
                    ->and($second->bootstrapCalls)->toBe(1)
                    ->and($second->revertCalls)->toBe(1)
                    ->and($tenancy->initialized)->toBeFalse()
                    ->and($tenancy->tenant)->toBeNull()
                    ->and($restorationEventDispatched)->toBeTrue();
            } finally {
                $tenancy->initialized = false;
                $tenancy->tenant = null;
            }
        },
    );
});

test('failed TenancyBootstrapped event restores the completed bootstrap', function () {
    runIsolatedTenantBootstrapScenario(
        function ($tenancy, Dispatcher $dispatcher): void {
            $bootstrapper = new class
            {
                public int $bootstrapCalls = 0;

                public int $revertCalls = 0;

                public function bootstrap($tenant): void
                {
                    $this->bootstrapCalls++;
                }

                public function revert(): void
                {
                    $this->revertCalls++;
                }
            };

            app()->instance($bootstrapper::class, $bootstrapper);

            $tenancy->getBootstrappersUsing = static fn ($tenant): array => [
                $bootstrapper::class,
            ];

            $failure = new RuntimeException(
                'Simulated TenancyBootstrapped listener failure.'
            );

            $dispatcher->listen(
                Events\TenancyBootstrapped::class,
                static function () use ($failure): never {
                    throw $failure;
                },
            );

            $restorationEventDispatched = false;

            $dispatcher->listen(
                Events\RevertedToCentralContext::class,
                static function () use (&$restorationEventDispatched): void {
                    $restorationEventDispatched = true;
                },
            );

            try {
                expect(
                    fn () => app(BootstrapTenancy::class)->handle(
                        new Events\TenancyInitialized($tenancy),
                    ),
                )->toThrow(
                    RuntimeException::class,
                    'Simulated TenancyBootstrapped listener failure.',
                );

                expect($bootstrapper->bootstrapCalls)->toBe(1)
                    ->and($bootstrapper->revertCalls)->toBe(1)
                    ->and($tenancy->initialized)->toBeFalse()
                    ->and($tenancy->tenant)->toBeNull()
                    ->and($restorationEventDispatched)->toBeTrue();
            } finally {
                $tenancy->initialized = false;
                $tenancy->tenant = null;
            }
        },
    );
});

test('rollback failure preserves the original bootstrap exception', function () {
    runIsolatedTenantBootstrapScenario(
        function ($tenancy, Dispatcher $dispatcher): void {
            $originalFailure = new RuntimeException(
                'Original tenant bootstrap failure.'
            );

            $rollbackFailure = new RuntimeException(
                'Simulated bootstrapper rollback failure.'
            );

            $first = new class
            {
                public int $revertCalls = 0;

                public function bootstrap($tenant): void
                {
                }

                public function revert(): void
                {
                    $this->revertCalls++;
                }
            };

            $second = new class($originalFailure, $rollbackFailure)
            {
                public int $revertCalls = 0;

                public function __construct(
                    private RuntimeException $originalFailure,
                    private RuntimeException $rollbackFailure,
                ) {
                }

                public function bootstrap($tenant): void
                {
                    throw $this->originalFailure;
                }

                public function revert(): void
                {
                    $this->revertCalls++;

                    throw $this->rollbackFailure;
                }
            };

            app()->instance($first::class, $first);
            app()->instance($second::class, $second);

            $tenancy->getBootstrappersUsing = static fn ($tenant): array => [
                $first::class,
                $second::class,
            ];

            $restorationEventDispatched = false;

            $dispatcher->listen(
                Events\RevertedToCentralContext::class,
                static function () use (&$restorationEventDispatched): void {
                    $restorationEventDispatched = true;
                },
            );

            try {
                $caught = null;

                try {
                    app(BootstrapTenancy::class)->handle(
                        new Events\TenancyInitialized($tenancy),
                    );
                } catch (\Throwable $exception) {
                    $caught = $exception;
                }

                expect($caught)->toBe($originalFailure)
                    ->and($first->revertCalls)->toBe(1)
                    ->and($second->revertCalls)->toBe(1)
                    ->and($tenancy->initialized)->toBeFalse()
                    ->and($tenancy->tenant)->toBeNull()
                    ->and($restorationEventDispatched)->toBeTrue();
            } finally {
                $tenancy->initialized = false;
                $tenancy->tenant = null;
            }
        },
    );
});
