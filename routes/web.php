<?php

use App\Http\Controllers\VerifyTenantEmailController;
use App\Http\Middleware\EnsureGhostPlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::domain(config('tenancy.central_domains')[0])->group(function () {
    Route::view('/', 'landing')->name('home');

    Route::get(
        '/tenant/email/verify/{tenant}/{id}/{hash}',
        VerifyTenantEmailController::class,
    )->middleware(['signed', 'throttle:6,1'])
        ->name('tenant.verification.verify');

    Route::livewire('/register', 'pages::auth.register')->name('register');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

    Route::middleware([
        'auth',
        'verified',
        EnsureGhostPlatformAdmin::class,
    ])->prefix('platform')->group(function () {
        Route::livewire(
            'failed-tenants',
            'pages::platform.failed-tenants',
        )->name('platform.tenants.failed');
    });

    require __DIR__.'/settings.php';
});
