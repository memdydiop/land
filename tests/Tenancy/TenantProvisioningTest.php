<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant provisioning creates the schema and applies the complete migration set', function () {
    $tenant = $this->testTenant('provisioning_v2');

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

    expect($tableCount)->toBe(71)
        ->and($migrationCount)->toBe(80);
});
