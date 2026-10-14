<?php

declare(strict_types=1);

namespace App\Tenancy;

use InvalidArgumentException;
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
        $databaseName = $this->validatedSchemaName($databaseName);
        $baseConfig['search_path'] = $databaseName.',public';

        return $baseConfig;
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $name = $this->validatedSchemaName($tenant->database()->getName());

        return $this->database()->statement(
            'CREATE SCHEMA "'.$name.'"'
        );
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $name = $this->validatedSchemaName($tenant->database()->getName());

        return $this->database()->statement(
            'DROP SCHEMA "'.$name.'" CASCADE'
        );
    }

    public function databaseExists(string $name): bool
    {
        $name = $this->validatedSchemaName($name);

        return $this->database()->selectOne(
            'SELECT 1
             FROM information_schema.schemata
             WHERE schema_name = ?',
            [$name],
        ) !== null;
    }

    private function validatedSchemaName(?string $name): string
    {
        // Tenant schema names are generated as "tenant_" + a normalized slug.
        // PostgreSQL identifiers are limited to 63 bytes.
        if (
            $name === null
            || preg_match('/\Atenant_[a-z0-9_]{1,56}\z/', $name) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid tenant PostgreSQL schema name.'
            );
        }

        return $name;
    }
}
