<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

class UniversalAuthenticationTest extends TenancyTestCase
{
    public function test_login_uses_central_users_on_central_domain(): void
    {
        $email = 'shared-auth-'.Str::lower((string) Str::ulid()).'@example.test';
        $password = 'Secret-password-123';

        $centralUser = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->from('http://localhost/login')
            ->post('http://localhost/login', [
                'email' => $email,
                'password' => $password,
            ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($centralUser);
    }

    public function test_login_uses_tenant_users_on_tenant_domain(): void
    {
        $tenant = $this->testTenant('universal_auth');

        $tenant->domains()->firstOrCreate([
            'domain' => 'universal-auth.test',
        ]);

        $email = 'shared-auth-'.Str::lower((string) Str::ulid()).'@example.test';
        $password = 'Secret-password-123';

        tenancy()->initialize($tenant);

        $tenantUser = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        tenancy()->end();

        $response = $this->from('http://universal-auth.test/login')
            ->post('http://universal-auth.test/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $response->assertRedirect('/dashboard');

        tenancy()->initialize($tenant);

        $this->assertAuthenticatedAs($tenantUser);

        tenancy()->end();
    }

    public function test_login_page_is_available_in_both_contexts(): void
    {
        $tenant = $this->testTenant('universal_view');

        $tenant->domains()->firstOrCreate([
            'domain' => 'universal-view.test',
        ]);

        $this->get('http://localhost/login')
            ->assertOk();

        $this->get('http://universal-view.test/login')
            ->assertOk();
    }
}