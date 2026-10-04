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
