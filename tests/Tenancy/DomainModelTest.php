<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use App\Models\Domain;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

class DomainModelTest extends TenancyTestCase
{
    public function test_domain_model_uses_ulids_and_persists_correctly(): void
    {
        $tenant = $this->testTenant('domain_model');

        $domainName = 'domain-model-'.Str::lower((string) Str::ulid()).'.test';

        $domain = $tenant->domains()->create([
            'domain' => $domainName,
        ]);

        $this->assertInstanceOf(Domain::class, $domain);
        $this->assertSame('string', $domain->getKeyType());
        $this->assertFalse($domain->getIncrementing());
        $this->assertSame(26, strlen($domain->getKey()));

        $this->assertDatabaseHas('domains', [
            'id' => $domain->getKey(),
            'domain' => $domainName,
            'tenant_id' => $tenant->getKey(),
        ]);
    }
}