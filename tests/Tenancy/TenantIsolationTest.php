<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant data is invisible from central context and other tenants', function () {
    $tenantA = $this->testTenant('isolation_a');
    $tenantB = $this->testTenant('isolation_b');

    $email = 'isolation-'.Str::lower(Str::random(16)).'@test.local';

    $userId = $tenantA->run(function () use ($email) {
        $user = User::create([
            'name' => 'Isolation User',
            'email' => $email,
            'password' => Hash::make('test-password'),
        ]);

        return $user->id;
    });

    $visibleInA = $tenantA->run(
        fn () => User::whereKey($userId)->exists()
    );

    $visibleInB = $tenantB->run(
        fn () => User::whereKey($userId)->exists()
    );

    $visibleInCentral = User::whereKey($userId)->exists();

    expect($visibleInA)->toBeTrue()
        ->and($visibleInB)->toBeFalse()
        ->and($visibleInCentral)->toBeFalse();

    expect(DB::selectOne(
        'SELECT current_schema() AS schema'
    )->schema)->toBe('testing');
});
