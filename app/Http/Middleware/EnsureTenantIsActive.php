<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = tenant();

        if (
            $currentTenant !== null
            && $currentTenant->status !== TenantStatus::Active->value
        ) {
            tenancy()->end();

            return response('', Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
