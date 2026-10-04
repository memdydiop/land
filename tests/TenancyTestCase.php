<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenancyTestCase extends TestCase
{
    /**
     * Tenant provisioning creates schemas and runs tenant migrations.
     *
     * Do not wrap the central test connection in a transaction for these tests.
     */
    protected array $connectionsToTransact = [];

    protected function testTenant(string $suffix = 'foundation'): Tenant
    {
        if (! preg_match('/^[a-z0-9_]+$/', $suffix)) {
            throw new RuntimeException('Invalid tenant test suffix.');
        }

        $slug = 'test-'.$suffix;
        $schema = 'tenant_test_'.$suffix;

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->first();

        $schemaExists = DB::connection('pgsql')->selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.schemata
                WHERE schema_name = ?
            ) AS exists',
            [$schema],
        )->exists;

        if ($tenant !== null && ! $schemaExists) {
            $tenant->delete();
            $tenant = null;
        }

        if ($tenant === null) {
            if ($schemaExists) {
                DB::connection('pgsql')->statement(
                    'DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE'
                );
            }

            $tenant = Tenant::create([
                'name' => 'Test '.ucwords(str_replace('_', ' ', $suffix)),
                'slug' => $slug,
                'status' => TenantStatus::Active->value,
                'schema_name' => $schema,
                'database_identifier' => null,
                'timezone' => 'Africa/Abidjan',
                'locale' => 'fr',
                'currency' => 'XOF',
                'data' => [
                    'purpose' => 'automated-tenancy-tests',
                ],
            ]);
        }

        return $tenant->fresh();
    }
}
