<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

final class CreateInitialAdmin
{
    /** @param array{name:string,email:string,password:string} $input */
    public function execute(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        if (User::role('Ghost')->exists()) {
            throw new RuntimeException('The LAND Ghost account has already been created.');
        }

        $role = Role::firstOrCreate(['name' => 'Ghost', 'guard_name' => 'web']);

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }
}
