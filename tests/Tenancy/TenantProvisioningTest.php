<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant provisioning creates the schema and applies the complete migration set', function () {
    $tenant = $this->testTenant('provisioning');

    expect($tenant->status)->toBe('active');

    $tableCount = (int) DB::selectOne(
        'SELECT COUNT(*) AS count
         FROM information_schema.tables
         WHERE table_schema = ?
           AND table_type = \'BASE TABLE\'',
        [$tenant->schema_name],
    )->count;

    $migrationCount = $tenant->run(
        fn () => DB::table('migrations')->count()
    );

    expect($tableCount)->toBe(67)
        ->and($migrationCount)->toBe(69);
});

test('tenant schema exposes the TERRA core relations', function () {
    $tenant = $this->testTenant('terra-schema');

    $tenant->run(function () {
        expect(DB::getSchemaBuilder()->hasTable('operations'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasTable('operation_lands'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasTable('subdivision_lands'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasTable('lots'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasTable('reservations'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasTable('sales'))->toBeTrue();

        expect(DB::getSchemaBuilder()->hasColumn('projects', 'operation_id'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasColumn('properties', 'operation_id'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasColumn('contracts', 'operation_id'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasColumn('contracts', 'sale_id'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasColumn('invoices', 'operation_id'))->toBeTrue()
            ->and(DB::getSchemaBuilder()->hasColumn('invoices', 'sale_id'))->toBeTrue();
    });
});
