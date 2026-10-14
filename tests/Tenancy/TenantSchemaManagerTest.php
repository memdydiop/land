<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\PostgreSQLSchemaManager;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('postgres schema manager rejects invalid schema identifiers before SQL execution', function () {
    $invalidSchemaNames = [
        'public',
        'tenant_with-hyphen',
        'tenant_name"; DROP SCHEMA public CASCADE; --',
        'tenant_'.str_repeat('a', 57),
    ];

    foreach ($invalidSchemaNames as $schemaName) {
        $tenant = new Tenant([
            'schema_name' => $schemaName,
        ]);

        $manager = app(PostgreSQLSchemaManager::class);
        $manager->setConnection('pgsql');

        expect(
            fn () => $manager->createDatabase($tenant)
        )->toThrow(InvalidArgumentException::class);

        expect(
            fn () => $manager->deleteDatabase($tenant)
        )->toThrow(InvalidArgumentException::class);

        expect(
            fn () => $manager->databaseExists($schemaName)
        )->toThrow(InvalidArgumentException::class);

        expect(
            fn () => $manager->makeConnectionConfig([], $schemaName)
        )->toThrow(InvalidArgumentException::class);
    }
});
