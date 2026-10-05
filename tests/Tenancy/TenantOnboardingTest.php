<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use App\Actions\Tenancy\OnboardTenant;
use App\Models\Domain;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
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
        $this->assertTrue($result->owner->hasRole('owner'));
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
}
