<?php

use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\PageAuditController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::view('/features', 'features')->name('features');

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::view('/dashboard', 'dashboard')->middleware('feature:dashboard')->name('dashboard');
    Route::view('/keywords', 'keywords.index')->middleware('feature:keywords')->name('keywords.index');
    Route::view('/rank-tracking', 'rank-tracking.index')->middleware('feature:rank_tracking')->name('rank-tracking.index');
    Route::get('/rank-tracking/{keywordId}', fn (int $keywordId) => view('rank-tracking.show', ['keywordId' => $keywordId]))
        ->whereNumber('keywordId')
        ->middleware('feature:rank_tracking')
        ->name('rank-tracking.show');
    Route::view('/backlinks', 'backlinks.index')->middleware('feature:backlinks')->name('backlinks.index');
    Route::view('/pages', 'pages.index')->middleware('feature:pages')->name('pages.index');
    Route::view('/custom-audit', 'pages.custom-audit')->middleware('feature:audits')->name('custom-audit.index');
    Route::get('/pages/{id}', fn (string $id) => view('pages.show', ['pageUrl' => $id]))
        ->middleware('feature:pages')
        ->name('pages.show')->where('id', '.*');
    Route::get('/page-audits/{pageAuditReport}', [PageAuditController::class, 'show'])
        ->middleware('feature:audits')
        ->name('page-audits.show');
    Route::get('/page-audits/{id}/download', [PageAuditController::class, 'download'])
        ->middleware(['feature:audits', 'throttle:30,1'])
        ->name('page-audits.download');
    Route::view('/websites', 'websites.index')->name('websites.index');
    Route::view('/team', 'team.index')->middleware('feature:team')->name('team.index');
    Route::view('/reports', 'reports.index')->middleware('feature:reports')->name('reports.index');
    Route::view('/settings', 'settings.index')->middleware('feature:settings')->name('settings.index');
});

Route::middleware(['auth', 'throttle:oauth'])->group(function () {
    Route::view('/onboarding', 'onboarding.index')->name('onboarding');
    Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
});
