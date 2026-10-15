<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;

class ActivateTenant
{
    public function execute(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Active->value,
        ]);
    }
}
