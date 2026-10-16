<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant roles and permissions are isolated and functional', function () {
    $tenant = $this->testTenant('permissions');

    $suffix = Str::lower(Str::random(12));
    $roleName = 'test-manager-'.$suffix;
    $permissionName = 'test.projects.view.'.$suffix;
    $email = 'permission-'.$suffix.'@test.local';

    $result = $tenant->run(function () use (
        $roleName,
        $permissionName,
        $email
    ) {
        $user = User::create([
            'name' => 'Permission Test User',
            'email' => $email,
            'password' => Hash::make('test-password'),
        ]);

        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $permission = Permission::create([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);
        $user->assignRole($role);
        $user->refresh();

        return [
            'has_role' => $user->hasRole($roleName),
            'has_permission' => $user->hasPermissionTo($permissionName),
            'roles_count' => Role::where('name', $roleName)->count(),
            'permissions_count' => Permission::where('name', $permissionName)->count(),
        ];
    });

    expect($result['has_role'])->toBeTrue()
        ->and($result['has_permission'])->toBeTrue()
        ->and($result['roles_count'])->toBe(1)
        ->and($result['permissions_count'])->toBe(1);
});

test('permission cache is isolated when switching between tenants', function () {
    $tenantA = $this->testTenant('permission_cache_a');
    $tenantB = $this->testTenant('permission_cache_b');

    $permissionName = 'test.shared.permission.'.strtolower(
        \Illuminate\Support\Str::random(16)
    );

    $tenantA->run(function () use ($permissionName): void {
        \App\Models\Permission::create([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);

        expect(
            \App\Models\Permission::findByName($permissionName, 'web')
        )->not->toBeNull();
    });

    $tenantB->run(function () use ($permissionName): void {
        expect(
            fn () => \App\Models\Permission::findByName(
                $permissionName,
                'web'
            )
        )->toThrow(
            \Spatie\Permission\Exceptions\PermissionDoesNotExist::class
        );
    });
});

test('permission cache resets across tenant transitions and central context', function () {
    $tenantA = $this->testTenant('permission_transition_a');
    $tenantB = $this->testTenant('permission_transition_b');

    $suffix = strtolower(\Illuminate\Support\Str::random(16));
    $centralPermission = 'test.central.'.$suffix;
    $permissionA = 'test.tenant-a.'.$suffix;
    $permissionB = 'test.tenant-b.'.$suffix;

    \App\Models\Permission::create([
        'name' => $centralPermission,
        'guard_name' => 'web',
    ]);

    expect(
        \App\Models\Permission::findByName($centralPermission, 'web')
    )->not->toBeNull();

    $tenantA->run(function () use (
        $tenantB,
        $centralPermission,
        $permissionA,
        $permissionB,
    ): void {
        \App\Models\Permission::create([
            'name' => $permissionA,
            'guard_name' => 'web',
        ]);

        expect(
            \App\Models\Permission::findByName($permissionA, 'web')
        )->not->toBeNull();

        expect(
            fn () => \App\Models\Permission::findByName($centralPermission, 'web')
        )->toThrow(
            \Spatie\Permission\Exceptions\PermissionDoesNotExist::class
        );

        $tenantB->run(function () use (
            $centralPermission,
            $permissionA,
            $permissionB,
        ): void {
            \App\Models\Permission::create([
                'name' => $permissionB,
                'guard_name' => 'web',
            ]);

            expect(
                \App\Models\Permission::findByName($permissionB, 'web')
            )->not->toBeNull();

            expect(
                fn () => \App\Models\Permission::findByName($permissionA, 'web')
            )->toThrow(
                \Spatie\Permission\Exceptions\PermissionDoesNotExist::class
            );

            expect(
                fn () => \App\Models\Permission::findByName($centralPermission, 'web')
            )->toThrow(
                \Spatie\Permission\Exceptions\PermissionDoesNotExist::class
            );
        });

        // Tenant::run() must restore tenant A after tenant B.
        expect(
            \App\Models\Permission::findByName($permissionA, 'web')
        )->not->toBeNull();

        expect(
            fn () => \App\Models\Permission::findByName($permissionB, 'web')
        )->toThrow(
            \Spatie\Permission\Exceptions\PermissionDoesNotExist::class
        );
    });

    // Leaving tenant A must restore the central permission context.
    expect(
        \App\Models\Permission::findByName($centralPermission, 'web')
    )->not->toBeNull();

    expect(
        fn () => \App\Models\Permission::findByName($permissionA, 'web')
    )->toThrow(
        \Spatie\Permission\Exceptions\PermissionDoesNotExist::class
    );
});
