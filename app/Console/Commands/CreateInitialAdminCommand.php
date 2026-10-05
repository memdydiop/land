<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Platform\CreateInitialAdmin;
use Illuminate\Console\Command;

final class CreateInitialAdminCommand extends Command
{
    protected $signature = 'land:admin
                            {name : Name of the Ghost administrator}
                            {email : Email address of the Ghost administrator}
                            {--password= : Password of the Ghost administrator}';

    protected $description = 'Create the one-time LAND Ghost platform administrator';

    public function handle(CreateInitialAdmin $action): int
    {
        $password = $this->option('password') ?: $this->secret('Password');

        $user = $action->execute([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
        ]);

        $this->info("Ghost administrator created: {$user->email}");

        return self::SUCCESS;
    }
}
