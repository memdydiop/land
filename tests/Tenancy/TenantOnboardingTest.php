<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use App\Actions\Tenancy\ActivateTenant;
use App\Actions\Tenancy\OnboardTenant;
use App\Contracts\Tenancy\TenantBootstrapper;
use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TenancyTestCase;

class TenantOnboardingTest extends TenancyTestCase
{
    public function test_onboarding_provisions_tenant_owner_and_default_land_domain(): void
    {
        $suffix = Str::lower(Str::random(12));
        $slug = 'acme-'.$suffix;
        $email = 'owner-'.$suffix.'@example.test';

        $result = app(OnboardTenant::class)->execute([
            'organization_name' => 'ACME Construction',
            'organization_slug' => $slug,
            'owner_name' => 'ACME Owner',
            'owner_email' => $email,
            'owner_password' => 'Secret-password-123',
            'owner_password_confirmation' => 'Secret-password-123',
            'timezone' => 'Africa/Abidjan',
            'locale' => 'fr',
            'currency' => 'XOF',
        ]);

        $this->assertSame('active', $result->tenant->status);
        $this->assertSame('tenant_'.Str::slug($slug, '_'), $result->tenant->schema_name);
        $this->assertSame($email, $result->owner->email);
        $this->assertTrue(
            $result->tenant->run(
                fn () => User::findOrFail($result->owner->id)->hasRole('owner')
            )
        );
        $this->assertSame($slug.'.land.ci', $result->domain->domain);
        $this->assertTrue($result->domain->is_primary);
        $this->assertNotNull($result->domain->verified_at);
        $this->assertSame(1, Domain::where('tenant_id', $result->tenant->id)->count());

        $tenantRole = $result->tenant->run(
            fn () => Role::where('name', 'owner')->where('guard_name', 'web')->first()
        );
        $tenantOwner = $result->tenant->run(
            fn () => User::where('email', $email)->first()
        );

        $this->assertNotNull($tenantRole);
        $this->assertNotNull($tenantOwner);
        $this->assertSame($result->owner->id, $tenantOwner->id);
    }

    public function test_onboarding_marks_tenant_failed_when_bootstrap_fails_after_provisioning(): void
    {
        $suffix = Str::lower(Str::random(12));
        $slug = 'failed-'.$suffix;

        $this->mock(TenantBootstrapper::class)
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('Simulated bootstrap failure.'));

        try {
            app(OnboardTenant::class)->execute([
                'organization_name' => 'Failed Tenant',
                'organization_slug' => $slug,
                'owner_name' => 'Test Owner',
                'owner_email' => 'owner-'.$suffix.'@example.test',
                'owner_password' => 'Secret-password-123',
                'owner_password_confirmation' => 'Secret-password-123',
            ]);

            $this->fail('Expected the bootstrap operation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated bootstrap failure.',
                $exception->getMessage()
            );
        }

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->assertSame('failed', $tenant->status);

        $this->assertDatabaseMissing('domains', [
            'tenant_id' => $tenant->id,
            'domain' => $slug.'.'.config('tenancy.land_domain', 'land.ci'),
        ]);

        $schemaExists = DB::connection('pgsql')->selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.schemata
                WHERE schema_name = ?
            ) AS exists',
            [$tenant->schema_name],
        )->exists;

        $this->assertTrue((bool) $schemaExists);
    }

    public function test_onboarding_rejects_reserved_platform_slugs(): void
    {
        try {
            app(OnboardTenant::class)->execute([
                'organization_name' => 'Reserved Slug Tenant',
                'organization_slug' => 'admin',
                'owner_name' => 'Test Owner',
                'owner_email' => 'reserved-'.Str::lower(Str::random(12)).'@example.test',
                'owner_password' => 'Secret-password-123',
                'owner_password_confirmation' => 'Secret-password-123',
            ]);

            $this->fail('Expected a reserved tenant slug to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }
    }

    public function test_onboarding_removes_domain_when_activation_fails(): void
    {
        $suffix = Str::lower(Str::random(12));
        $slug = 'activation-failure-'.$suffix;
        $domainName = $slug.'.'.config('tenancy.land_domain', 'land.ci');

        $this->mock(ActivateTenant::class)
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('Simulated activation failure.'));

        try {
            app(OnboardTenant::class)->execute([
                'organization_name' => 'Activation Failure Tenant',
                'organization_slug' => $slug,
                'owner_name' => 'Test Owner',
                'owner_email' => 'activation-'.$suffix.'@example.test',
                'owner_password' => 'Secret-password-123',
                'owner_password_confirmation' => 'Secret-password-123',
            ]);

            $this->fail('Expected tenant activation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated activation failure.',
                $exception->getMessage(),
            );
        }

        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

        $this->assertSame(TenantStatus::Failed->value, $tenant->status);
        $this->assertDatabaseMissing('domains', [
            'tenant_id' => $tenant->getKey(),
            'domain' => $domainName,
        ]);

        $schemaExists = DB::connection('pgsql')
            ->selectOne(
                'SELECT EXISTS (
                    SELECT 1 FROM information_schema.schemata
                    WHERE schema_name = ?
                ) AS exists',
                [$tenant->schema_name],
            )->exists;

        $this->assertTrue((bool) $schemaExists);
    }
}
