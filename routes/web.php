<?php

use App\Http\Middleware\EnsureGhostPlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::domain(config('tenancy.central_domains')[0])->group(function () {
    Route::view('/', 'landing')->name('home');

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
