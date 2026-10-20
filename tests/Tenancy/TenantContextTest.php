<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);


test('tenant run restores central context when callback throws', function () {
    $tenant = $this->testTenant('context_exception');

    expect(fn () => $tenant->run(function (): void {
        throw new RuntimeException('Simulated tenant operation failure.');
    }))->toThrow(RuntimeException::class);

    expect(tenancy()->initialized)->toBeFalse()
        ->and(tenant())->toBeNull()
        ->and(
            DB::selectOne('SELECT current_schema() AS schema')->schema
        )->toBe('testing');
});



test('nested tenant run restores previous tenant when callback throws', function () {
    $tenantA = $this->testTenant('context_outer_exception');
    $tenantB = $this->testTenant('context_inner_exception');

    $tenantA->run(function () use ($tenantA, $tenantB): void {
        expect(tenant()->getTenantKey())->toBe($tenantA->getTenantKey())
            ->and(
                DB::selectOne('SELECT current_schema() AS schema')->schema
            )->toBe($tenantA->schema_name);

        try {
            $tenantB->run(function (): void {
                throw new RuntimeException('Simulated nested tenant failure.');
            });

            throw new RuntimeException('Expected the nested callback to throw.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())
                ->toBe('Simulated nested tenant failure.');
        }

        expect(tenancy()->initialized)->toBeTrue()
            ->and(tenant()->getTenantKey())->toBe($tenantA->getTenantKey())
            ->and(
                DB::selectOne('SELECT current_schema() AS schema')->schema
            )->toBe($tenantA->schema_name);
    });

    expect(tenancy()->initialized)->toBeFalse()
        ->and(tenant())->toBeNull()
        ->and(
            DB::selectOne('SELECT current_schema() AS schema')->schema
        )->toBe('testing');
});


test('failed tenant bootstrap restores central context when schema is missing', function () {
    $tenant = $this->testTenant('missing_schema_context');
    $schema = $tenant->schema_name;
    $originalEnvironment = app()->environment();

    DB::connection('pgsql')->statement(
        'DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE'
    );

    $this->app->instance('env', 'local');

    try {
        expect(fn () => $tenant->run(fn () => null))
            ->toThrow(
                \Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException::class
            );

        expect(tenancy()->initialized)->toBeFalse()
            ->and(tenant())->toBeNull()
            ->and(
                DB::connection('pgsql')->selectOne(
                    'SELECT current_schema() AS schema'
                )->schema
            )->toBe('testing');
    } finally {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->app->instance('env', $originalEnvironment);

        $tenant->delete();

        DB::connection('pgsql')->statement(
            'DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE'
        );
    }
});


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
