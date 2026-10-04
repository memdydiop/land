<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing(
            static function (TenantWithDatabase $tenant): string {
                if (! $tenant instanceof Tenant) {
                    throw new \LogicException(
                        'The configured tenancy tenant model must be '.Tenant::class.'.'
                    );
                }

                return $tenant->getSchemaName();
            }
        );

        $this->bootEvents();
    }

    protected function bootEvents(): void
    {
        Event::listen(
            Events\TenantCreated::class,
            JobPipeline::make([
                CreateDatabase::class,
                MigrateDatabase::class,
            ])
                ->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })
                ->shouldBeQueued(false)
                ->toListener(),
        );

        Event::listen(
            Events\TenancyInitialized::class,
            Listeners\BootstrapTenancy::class,
        );

        Event::listen(
            Events\TenancyEnded::class,
            Listeners\RevertToCentralContext::class,
        );
    }
}
