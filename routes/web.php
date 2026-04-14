<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleOAuthController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/keywords', 'keywords.index')->name('keywords.index');
    Route::view('/pages', 'pages.index')->name('pages.index');
    Route::view('/pages/{id}', 'pages.show')->name('pages.show');
    Route::view('/websites', 'websites.index')->name('websites.index');
    Route::view('/settings', 'settings.index')->name('settings.index');
});

Route::middleware(['auth', 'throttle:oauth'])->group(function () {
    Route::view('/onboarding', 'onboarding.index')->name('onboarding');
    Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
});
