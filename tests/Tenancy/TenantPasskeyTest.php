<?php

declare(strict_types=1);

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkeys;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant passkeys use the custom ULID model and persist correctly', function () {
    $tenant = $this->testTenant('passkeys');

    $result = $tenant->run(function () {
        $user = User::create([
            'name' => 'Passkey Test User',
            'email' => 'passkey-'.Str::lower(Str::random(16)).'@test.local',
            'password' => Hash::make('test-password'),
        ]);

        $passkey = new Passkey;
        $passkey->name = 'Foundation Test Passkey';
        $passkey->credential_id = Str::random(64);
        $passkey->credential = [
            'type' => 'public-key',
            'counter' => 0,
            'test' => true,
        ];
        $passkey->user_id = $user->id;
        $passkey->save();

        return [
            'configured_model' => Passkeys::passkeyModel(),
            'id' => $passkey->id,
            'id_length' => strlen($passkey->id),
            'key_type' => $passkey->getKeyType(),
            'incrementing' => $passkey->getIncrementing(),
            'persisted' => Passkey::whereKey($passkey->id)->exists(),
            'user_id' => $passkey->user_id,
        ];
    });

    expect($result['configured_model'])->toBe(Passkey::class)
        ->and($result['id_length'])->toBe(26)
        ->and($result['key_type'])->toBe('string')
        ->and($result['incrementing'])->toBeFalse()
        ->and($result['persisted'])->toBeTrue()
        ->and($result['user_id'])->not->toBeEmpty();
});
