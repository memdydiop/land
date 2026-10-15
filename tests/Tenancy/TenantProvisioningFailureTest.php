<?php

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenant;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('tenant is marked failed and schema retained when migrations fail', function () {
    $suffix = Str::lower((string) Str::ulid());
    $slug = 'migration-failure-'.$suffix;
    $schema = 'tenant_'.Str::slug($slug, '_');

    Artisan::shouldReceive('call')
        ->once()
        ->andThrow(new RuntimeException('Simulated tenant migration failure.'));

    try {
        expect(
            fn () => app(CreateTenant::class)->execute([
                'name' => 'Migration Failure Tenant',
                'slug' => $slug,
            ])
        )->toThrow(
            RuntimeException::class,
            'Simulated tenant migration failure.'
        );

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->firstOrFail();

        expect($tenant->status)->toBe(TenantStatus::Failed->value);

        $schemaExists = DB::connection('pgsql')->selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.schemata
                WHERE schema_name = ?
            ) AS exists',
            [$schema],
        )->exists;

        expect((bool) $schemaExists)->toBeTrue();
    } finally {
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->first();

        if ($tenant !== null) {
            $manager = $tenant->database()->manager();

            if ($manager->databaseExists($schema)) {
                $manager->deleteDatabase($tenant);
            }

            $tenant->delete();
        }
    }
});
