<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\TenancyTestCase;

uses(TenancyTestCase::class);

test('Livewire file routes initialize tenancy and enforce active status', function () {
    foreach (['livewire.upload-file', 'livewire.preview-file'] as $name) {
        expect(Route::getRoutes()->getByName($name))->not->toBeNull();
    }

    $uploadMiddleware = (array) config(
        'livewire.temporary_file_upload.middleware'
    );

    expect($uploadMiddleware)
        ->toContain('universal')
        ->toContain(InitializeTenancyByDomain::class)
        ->toContain(EnsureTenantIsActive::class);

    expect(FilePreviewController::$middleware)
        ->toContain('universal')
        ->toContain(InitializeTenancyByDomain::class)
        ->toContain(EnsureTenantIsActive::class);
});

test('inactive tenant cannot use Livewire upload or preview endpoints', function () {
    $tenant = $this->testTenant('livewire_file_status');

    $domain = 'livewire-files-'.Str::lower((string) Str::ulid()).'.test';

    $tenant->domains()->create([
        'domain' => $domain,
    ]);

    $tenant->update([
        'status' => TenantStatus::Failed->value,
    ]);

    $uploadPath = parse_url(
        route('livewire.upload-file', [], false),
        PHP_URL_PATH,
    );

    $previewPath = parse_url(
        route('livewire.preview-file', [
            'filename' => 'private-preview-test.jpg',
        ], false),
        PHP_URL_PATH,
    );

    $this->post('http://'.$domain.$uploadPath, [])
        ->assertNotFound();

    $this->get('http://'.$domain.$previewPath)
        ->assertNotFound();
});
