<?php

declare(strict_types=1);

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager as BasePostgreSQLSchemaManager;

class PostgreSQLSchemaManager extends BasePostgreSQLSchemaManager
{
    /**
     * @param array<string, mixed> $baseConfig
     * @return array<string, mixed>
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['search_path'] = $databaseName.',public';

        return $baseConfig;
    }
}
