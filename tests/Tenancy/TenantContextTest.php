<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant context switches search path and restores central context', function () {
    $tenant = $this->testTenant('context');

    $before = [
        'schema' => DB::selectOne(
            'SELECT current_schema() AS schema'
        )->schema,
        'search_path' => DB::selectOne(
            "SELECT current_setting('search_path') AS path"
        )->path,
    ];

    $inside = $tenant->run(function () {
        return [
            'schema' => DB::selectOne(
                'SELECT current_schema() AS schema'
            )->schema,
            'search_path' => DB::selectOne(
                "SELECT current_setting('search_path') AS path"
            )->path,
        ];
    });

    $after = [
        'schema' => DB::selectOne(
            'SELECT current_schema() AS schema'
        )->schema,
        'search_path' => DB::selectOne(
            "SELECT current_setting('search_path') AS path"
        )->path,
    ];

    expect($before['schema'])->toBe('testing')
        ->and($before['search_path'])->toContain('testing')
        ->and($inside['schema'])->toBe($tenant->schema_name)
        ->and($inside['search_path'])->toContain($tenant->schema_name)
        ->and($after['schema'])->toBe('testing')
        ->and($after['search_path'])->toContain('testing');
});
