<?php

declare(strict_types=1);

namespace App\Contracts\Tenancy;

use App\Models\Role;
use App\Models\Tenant;

interface TenantBootstrapper
{
    public function execute(Tenant $tenant): Role;
}
