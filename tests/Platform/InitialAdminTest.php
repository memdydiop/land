<?php

declare(strict_types=1);

namespace Tests\Platform;

use App\Actions\Platform\CreateInitialAdmin;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class InitialAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_initial_ghost_account_is_created_in_central_context(): void
    {
        // Keep this one-time bootstrap test repeatable in the isolated test database.
        User::query()
            ->whereHas('roles', fn ($query) => $query
                ->where('name', 'Ghost')
                ->where('guard_name', 'web'))
            ->get()
            ->each(fn (User $user) => $user->delete());

        $email = 'ghost-'.Str::lower((string) Str::ulid()).'@example.test';

        $user = app(CreateInitialAdmin::class)->execute([
            'name' => 'LAND Ghost',
            'email' => $email,
            'password' => 'Secret-password-123',
        ]);

        $this->assertTrue($user->hasRole('Ghost'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(1, Role::where('name', 'Ghost')->count());
        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertSame(0, Tenant::count());
    }
}
