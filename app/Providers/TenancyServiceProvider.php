<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public static string $controllerNamespace = '';

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

        Livewire::setUpdateRoute(function ($handle, $path) {
            return Route::post($path, $handle)
                ->middleware([
                    'web',
                    'universal',
                    InitializeTenancyByDomain::class,
                ]);
        });

        $this->mapRoutes();
        $this->makeTenancyMiddlewareHighestPriority();
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

    protected function mapRoutes(): void
    {
        $this->app->booted(function (): void {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomain::class,
            InitializeTenancyBySubdomain::class,
            InitializeTenancyByDomainOrSubdomain::class,
            InitializeTenancyByPath::class,
            InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[Kernel::class]
                ->prependToMiddlewarePriority($middleware);
        }
    }
}
