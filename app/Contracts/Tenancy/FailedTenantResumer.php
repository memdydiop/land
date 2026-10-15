<?php

declare(strict_types=1);

namespace App\Contracts\Tenancy;

use App\Actions\Tenancy\TenantOnboardingResult;
use App\Models\Tenant;

interface FailedTenantResumer
{
    /**
     * @param  array<string, mixed>  $ownerInput
     */
    public function execute(
        Tenant $tenant,
        array $ownerInput,
    ): TenantOnboardingResult;
}
