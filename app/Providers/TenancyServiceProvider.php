<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
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
                return $tenant->getAttribute('schema_name');
            }
        );

        $this->bootEvents();
    }

    protected function bootEvents(): void
    {
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