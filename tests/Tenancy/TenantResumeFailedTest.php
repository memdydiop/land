<?php

declare(strict_types=1);

use App\Actions\Tenancy\ActivateTenant;
use App\Actions\Tenancy\ResumeFailedTenant;
use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

function resumeFailedTenantPayload(string $email): array
{
    return [
        'owner_name' => 'Recovery Owner',
        'owner_email' => $email,
        'owner_password' => 'Secret-password-123',
        'owner_password_confirmation' => 'Secret-password-123',
    ];
}

test('failed tenant can be resumed with an existing schema', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant('resume_'.$suffix);
    $email = 'resume_'.$suffix.'@example.test';

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    $result = app(ResumeFailedTenant::class)->execute(
        $tenant,
        resumeFailedTenantPayload($email),
    );

    expect($result->tenant->status)->toBe(TenantStatus::Active->value);
    expect($result->owner->email)->toBe($email);
    expect($result->domain->domain)
        ->toBe($tenant->slug.'.'.config('tenancy.land_domain', 'land.ci'));

    expect(
        $tenant->run(fn () => User::where('email', $email)->count())
    )->toBe(1);

    expect(
        Domain::where('tenant_id', $tenant->getKey())->count()
    )->toBe(1);
});

test('failed tenant recovery recreates an absent schema without dropping a schema during recovery', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant('resume_schema_'.$suffix);

    $schemaManager = $tenant->database()->manager();
    $schemaName = $tenant->database()->getName();

    expect($schemaManager->databaseExists($schemaName))->toBeTrue();

    // This deletion sets up the absent-schema scenario in an isolated test tenant.
    $schemaManager->deleteDatabase($tenant);

    expect($schemaManager->databaseExists($schemaName))->toBeFalse();

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    $email = 'resume_schema_'.$suffix.'@example.test';

    $result = app(ResumeFailedTenant::class)->execute(
        $tenant,
        resumeFailedTenantPayload($email),
    );

    expect($result->tenant->status)->toBe(TenantStatus::Active->value);
    expect($schemaManager->databaseExists($schemaName))->toBeTrue();

    expect(
        $tenant->run(fn () => User::where('email', $email)->exists())
    )->toBeTrue();
});

test('failed tenant recovery reuses an existing owner and primary domain', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant('resume_existing_'.$suffix);
    $email = 'existing-owner-'.$suffix.'@example.test';
    $originalPassword = 'Original-password-123';

    $existingOwner = $tenant->run(function () use (
        $email,
        $originalPassword,
    ): User {
        Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Original Owner',
            'email' => $email,
            'password' => $originalPassword,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('owner');

        return $user->fresh();
    });

    $domainName = $tenant->slug.'.'.config('tenancy.land_domain', 'land.ci');

    $tenant->domains()->create([
        'domain' => $domainName,
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    // Password and name are omitted: an existing owner must be preserved.
    $result = app(ResumeFailedTenant::class)->execute($tenant, [
        'owner_email' => $email,
    ]);

    expect($result->owner->getKey())->toBe($existingOwner->getKey());
    expect($result->owner->name)->toBe('Original Owner');
    expect(Hash::check(
        $originalPassword,
        $result->owner->password,
    ))->toBeTrue();

    expect(
        Domain::where('tenant_id', $tenant->getKey())
            ->where('domain', $domainName)
            ->count()
    )->toBe(1);

    expect($result->tenant->status)->toBe(TenantStatus::Active->value);
});

test('failed tenant recovery does not promote an existing non-owner implicitly', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant('resume_nonowner_'.substr($suffix, -12));
    $email = 'existing-user-'.$suffix.'@example.test';

    $existingUser = $tenant->run(function () use ($email): User {
        $role = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Existing Viewer',
            'email' => $email,
            'password' => 'Original-password-123',
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user->fresh();
    });

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    expect(
        fn () => app(ResumeFailedTenant::class)->execute($tenant, [
            'owner_email' => $email,
        ])
    )->toThrow(
        LogicException::class,
        'already exists but is not an owner',
    );

    expect($tenant->fresh()->status)->toBe(TenantStatus::Failed->value);

    expect(
        $tenant->run(
            fn () => User::findOrFail($existingUser->getKey())->hasRole('owner')
        )
    )->toBeFalse();

    expect(
        Domain::where('tenant_id', $tenant->getKey())->count()
    )->toBe(0);
});

test('failed tenant recovery restores a pre-existing domain when activation fails', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant(
        'resume_domain_restore_'.substr($suffix, -12)
    );

    $domainName = $tenant->slug.'.'.config('tenancy.land_domain', 'land.ci');

    $domain = $tenant->domains()->create([
        'domain' => $domainName,
        'is_primary' => false,
        'verified_at' => null,
    ]);

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    $activation = Mockery::mock(
        ActivateTenant::class
    );

    $activation->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Simulated activation failure'));

    app()->instance(
        ActivateTenant::class,
        $activation,
    );

    expect(
        fn () => app(ResumeFailedTenant::class)->execute(
            $tenant,
            resumeFailedTenantPayload(
                'restore-domain-'.$suffix.'@example.test'
            ),
        )
    )->toThrow(
        RuntimeException::class,
        'Simulated activation failure',
    );

    $restoredDomain = Domain::query()->findOrFail($domain->getKey());

    expect((bool) $restoredDomain->is_primary)->toBeFalse();
    expect($restoredDomain->verified_at)->toBeNull();
    expect($tenant->fresh()->status)->toBe(TenantStatus::Failed->value);
});

test('failed tenant recovery refuses a tenant that is not failed', function () {
    $tenant = $this->testTenant(
        'resume_active_'.Str::lower((string) Str::ulid())
    );

    expect(
        fn () => app(ResumeFailedTenant::class)->execute($tenant, [
            'owner_email' => 'owner@example.test',
        ])
    )->toThrow(LogicException::class, 'Only failed tenants can be resumed.');

    expect($tenant->fresh()->status)->toBe(TenantStatus::Active->value);
});

test('failed tenant recovery refuses a domain belonging to another tenant', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant('resume_conflict_'.$suffix);
    $otherTenant = $this->testTenant('resume_holder_'.$suffix);

    $domainName = $tenant->slug.'.'.config('tenancy.land_domain', 'land.ci');

    $otherTenant->domains()->create([
        'domain' => $domainName,
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    expect(
        fn () => app(ResumeFailedTenant::class)->execute(
            $tenant,
            resumeFailedTenantPayload('resume_conflict_'.$suffix.'@example.test'),
        )
    )->toThrow(RuntimeException::class, 'belongs to another tenant');

    expect($tenant->fresh()->status)->toBe(TenantStatus::Failed->value);

    expect(
        Domain::where('domain', $domainName)->value('tenant_id')
    )->toBe($otherTenant->getKey());

    expect(
        $tenant->run(fn () => User::count())
    )->toBe(0);
});

test('failed tenant recovery keeps the schema and failed status when migration returns a non-zero code', function () {
    $suffix = Str::lower((string) Str::ulid());
    $tenant = $this->testTenant('resume_migrate_failure_'.$suffix);
    $schemaManager = $tenant->database()->manager();
    $schemaName = $tenant->database()->getName();

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    Artisan::shouldReceive('call')
        ->once()
        ->andReturn(1);

    expect(
        fn () => app(ResumeFailedTenant::class)->execute(
            $tenant,
            resumeFailedTenantPayload('resume_failure-'.$suffix.'@example.test'),
        )
    )->toThrow(RuntimeException::class, 'migrations failed with exit code 1');

    expect($tenant->fresh()->status)->toBe(TenantStatus::Failed->value);
    expect($schemaManager->databaseExists($schemaName))->toBeTrue();

    expect(
        Domain::where('tenant_id', $tenant->getKey())->count()
    )->toBe(0);
});
