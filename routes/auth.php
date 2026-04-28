<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\GoogleOAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('auth/google/sso', [GoogleOAuthController::class, 'ssoRedirect'])
        ->middleware('throttle:oauth')
        ->name('google.sso.redirect');
    Route::get('auth/google/sso/callback', [GoogleOAuthController::class, 'ssoCallback'])
        ->middleware('throttle:oauth')
        ->name('google.sso.callback');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
