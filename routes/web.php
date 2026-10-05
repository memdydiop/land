<?php

use Illuminate\Support\Facades\Route;

Route::domain(config('tenancy.central_domains')[0])->group(function () {
    Route::view('/', 'landing')->name('home');

    Route::livewire('/register', 'pages::auth.register')->name('register');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

    require __DIR__.'/settings.php';
});
