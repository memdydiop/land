<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    EnsureTenantIsActive::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::view('/dashboard', 'dashboard')->name('tenant.dashboard');
    });

    Route::middleware(['auth'])->group(function () {
        Route::redirect('/settings', '/settings/profile')
            ->name('tenant.settings');

        Route::livewire('/settings/profile', 'pages::settings.profile')
            ->name('tenant.profile.edit');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::livewire('/settings/appearance', 'pages::settings.appearance')
            ->name('tenant.appearance.edit');

        Route::livewire('/settings/security', 'pages::settings.security')
            ->middleware(['password.confirm'])
            ->name('tenant.security.edit');
    });

    Route::get('/.well-known/passkey-endpoints', function () {
        return response()->json([
            'enroll' => route('tenant.security.edit'),
            'manage' => route('tenant.security.edit'),
        ]);
    })->name('tenant.well-known.passkeys');
});
