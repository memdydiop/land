<?php

declare(strict_types=1);

use App\Actions\Platform\ResumeFailedTenantFromPlatform;
use App\Actions\Tenancy\TenantOnboardingResult;
use App\Contracts\Tenancy\FailedTenantResumer;
use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\PlatformAuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use RuntimeException;

function platformAuditTestUser(bool $ghost = false): User
{
    $user = User::create([
        'name' => $ghost ? 'Platform Ghost' : 'Ordinary User',
        'email' => ($ghost ? 'ghost-' : 'user-')
            .Str::lower((string) Str::ulid()).'@example.test',
        'password' => 'Secret-password-123',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
    ])->save();

    if ($ghost) {
        $role = Role::firstOrCreate([
            'name' => 'Ghost',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }

    return $user;
}

function platformAuditTestFailedTenant(): Tenant
{
    $suffix = Str::lower((string) Str::ulid());

    return Tenant::withoutEvents(fn (): Tenant => Tenant::create([
        'id' => (string) Str::ulid(),
        'name' => 'Audit Test '.$suffix,
        'slug' => 'audit-failed-'.$suffix,
        'status' => TenantStatus::Failed->value,
        'schema_name' => 'tenant_audit_'.$suffix,
        'database_identifier' => null,
        'timezone' => 'Africa/Abidjan',
        'locale' => 'fr',
        'currency' => 'XOF',
        'data' => ['purpose' => 'platform-audit-tests'],
    ]));
}

test('only Ghost can access the central failed tenant page', function () {
    $ordinaryUser = platformAuditTestUser();

    /** @var TestCase $testCase */
    $testCase = $this;

    $testCase->actingAs($ordinaryUser)
        ->get(route('platform.tenants.failed'))
        ->assertForbidden();
});

test('Ghost can view failed tenants on the central platform', function () {
    $ghost = platformAuditTestUser(ghost: true);
    $tenant = platformAuditTestFailedTenant();

    /** @var TestCase $testCase */
    $testCase = $this;

    $testCase->actingAs($ghost)
        ->get(route('platform.tenants.failed'))
        ->assertOk()
        ->assertSee('Reprise des tenants en échec')
        ->assertSee($tenant->slug)
        ->assertSee('Préparer la reprise');
});

test('Ghost can inspect the central audit history', function () {
    $ghost = platformAuditTestUser(ghost: true);
    $tenant = platformAuditTestFailedTenant();

    PlatformAuditLog::query()->create([
        'event' => 'tenant.resume',
        'actor_id' => $ghost->getKey(),
        'actor_name' => $ghost->name,
        'actor_email' => $ghost->email,
        'tenant_id' => $tenant->getKey(),
        'tenant_name' => $tenant->name,
        'tenant_slug' => $tenant->slug,
        'status' => 'failed',
        'failure_code' => 'provisioning_failed',
        'started_at' => now()->subMinute(),
        'completed_at' => now(),
    ]);

    /** @var TestCase $testCase */
    $testCase = $this;

    $testCase->actingAs($ghost)
        ->get(route('platform.tenants.failed'))
        ->assertOk()
        ->assertSee('Journal des opérations')
        ->assertSee($ghost->email)
        ->assertSee($tenant->slug)
        ->assertSee('provisioning_failed')
        ->assertSee('failed');
});

test('non-Ghost cannot trigger tenant recovery or create an audit entry', function () {
    $ordinaryUser = platformAuditTestUser();
    $tenant = platformAuditTestFailedTenant();

    expect(
        fn () => app(ResumeFailedTenantFromPlatform::class)->execute(
            $ordinaryUser,
            (string) $tenant->getKey(),
            [],
        )
    )->toThrow(AuthorizationException::class);

    expect(PlatformAuditLog::query()->count())->toBe(0);
});

test('platform recovery records success and failure without sensitive values', function () {
    $ghost = platformAuditTestUser(ghost: true);
    $tenant = platformAuditTestFailedTenant();
    $secret = 'never-store-this-password';

    $successfulResult = new TenantOnboardingResult(
        $tenant->forceFill(['status' => TenantStatus::Active->value]),
        new User,
        new Domain,
    );

    $resumer = Mockery::mock(FailedTenantResumer::class);
    $resumer->shouldReceive('execute')
        ->once()
        ->andReturn($successfulResult);

    app()->instance(FailedTenantResumer::class, $resumer);

    request()->headers->set('User-Agent', 'Terra-Audit-Test/1.0');

    $result = app(ResumeFailedTenantFromPlatform::class)->execute(
        $ghost,
        (string) $tenant->getKey(),
        [
            'owner_email' => 'owner@example.test',
            'owner_password' => $secret,
            'owner_password_confirmation' => $secret,
        ],
    );

    expect($result)->toBe($successfulResult);

    $successLog = PlatformAuditLog::query()
        ->where('status', 'succeeded')
        ->firstOrFail();

    expect($successLog->event)->toBe('tenant.resume');
    expect($successLog->actor_id)->toBe($ghost->getKey());
    expect($successLog->tenant_id)->toBe($tenant->getKey());
    expect($successLog->failure_code)->toBeNull();
    expect($successLog->completed_at)->not->toBeNull();
    expect($successLog->user_agent)->toBe('Terra-Audit-Test/1.0');
    expect($successLog->ip_address)->toBe(request()->ip());
    expect(json_encode($successLog->getAttributes()))->not->toContain($secret);

    $failedTenant = platformAuditTestFailedTenant();
    $rawExceptionMessage = 'sensitive diagnostic must not be persisted';

    $failingResumer = Mockery::mock(FailedTenantResumer::class);
    $failingResumer->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException($rawExceptionMessage));

    app()->instance(FailedTenantResumer::class, $failingResumer);

    expect(
        fn () => app(ResumeFailedTenantFromPlatform::class)->execute(
            $ghost,
            (string) $failedTenant->getKey(),
            ['owner_email' => 'owner@example.test'],
        )
    )->toThrow(RuntimeException::class, $rawExceptionMessage);

    $failureLog = PlatformAuditLog::query()
        ->where('tenant_id', $failedTenant->getKey())
        ->firstOrFail();

    expect($failureLog->status)->toBe('failed');
    expect($failureLog->failure_code)->toBe('provisioning_failed');
    expect($failureLog->completed_at)->not->toBeNull();
    expect(json_encode($failureLog->getAttributes()))->not->toContain($rawExceptionMessage);
});
