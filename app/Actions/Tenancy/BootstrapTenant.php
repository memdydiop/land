<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Role;
use App\Models\Tenant;

final class BootstrapTenant
{
    public function execute(Tenant $tenant): Role
    {
        return $tenant->run(
            fn (): Role => Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web'])
        );
    }
}
