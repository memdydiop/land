<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\TenancyTestCase;

class UniversalLivewireTest extends TenancyTestCase
{
    public function test_livewire_update_route_is_universal_and_tenant_aware(): void
    {
        $route = RouteFacade::getRoutes()->getByName('livewire.update');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('POST', $route->methods()[0]);

        $middleware = $route->middleware();

        $this->assertContains('universal', $middleware);
        $this->assertContains(
            InitializeTenancyByDomain::class,
            $middleware,
        );
    }
}
