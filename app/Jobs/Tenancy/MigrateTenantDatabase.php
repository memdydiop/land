<?php

declare(strict_types=1);

namespace App\Jobs\Tenancy;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Stancl\Tenancy\Jobs\MigrateDatabase as StanclMigrateDatabase;

final class MigrateTenantDatabase extends StanclMigrateDatabase
{
    public function handle()
    {
        $exitCode = Artisan::call('tenants:migrate', [
            '--tenants' => [$this->tenant->getTenantKey()],
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Tenant [%s] migrations failed with exit code %d.',
                $this->tenant->getTenantKey(),
                $exitCode,
            ));
        }
    }
}
