<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;

final readonly class TenantOnboardingResult
{
    public function __construct(
        public Tenant $tenant,
        public User $owner,
        public Domain $domain,
    ) {}
}
