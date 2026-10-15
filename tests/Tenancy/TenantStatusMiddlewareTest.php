<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant domains reject tenants that are not active', function () {
    $tenant = $this->testTenant('status_guard');
    $domain = 'status-'.Str::lower((string) Str::ulid()).'.test';

    $tenant->domains()->create([
        'domain' => $domain,
    ]);

    $inactiveStatuses = [
        TenantStatus::Pending,
        TenantStatus::Provisioning,
        TenantStatus::Suspended,
        TenantStatus::Failed,
        TenantStatus::Cancelled,
    ];

    foreach ($inactiveStatuses as $status) {
        $tenant->update([
            'status' => $status->value,
        ]);

        $this->get('http://'.$domain.'/')->assertNotFound();
    }

    $tenant->update([
        'status' => TenantStatus::Active->value,
    ]);

    $this->get('http://'.$domain.'/')->assertOk();
});
