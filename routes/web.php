<?php

use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\PageAuditController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/keywords', 'keywords.index')->name('keywords.index');
    Route::view('/rank-tracking', 'rank-tracking.index')->name('rank-tracking.index');
    Route::view('/backlinks', 'backlinks.index')->name('backlinks.index');
    Route::view('/pages', 'pages.index')->name('pages.index');
    Route::view('/custom-audit', 'pages.custom-audit')->name('custom-audit.index');
    Route::get('/pages/{id}', fn (string $id) => view('pages.show', ['pageUrl' => $id]))->name('pages.show')->where('id', '.*');
    Route::get('/page-audits/{pageAuditReport}', [PageAuditController::class, 'show'])->name('page-audits.show');
    Route::get('/page-audits/{id}/download', [PageAuditController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('page-audits.download');
    Route::view('/websites', 'websites.index')->name('websites.index');
    Route::view('/team', 'team.index')->name('team.index');
    Route::view('/reports', 'reports.index')->name('reports.index');
    Route::view('/settings', 'settings.index')->name('settings.index');
});

Route::middleware(['auth', 'throttle:oauth'])->group(function () {
    Route::view('/onboarding', 'onboarding.index')->name('onboarding');
    Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
});
