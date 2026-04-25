<?php

use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\PageAuditController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\ClientImpersonationController;
use App\Http\Controllers\Admin\PluginAdoptionController as AdminPluginAdoptionController;
use App\Http\Controllers\Admin\PluginReleaseController as AdminPluginReleaseController;
use App\Http\Controllers\WordPressConnectController;
use App\Http\Controllers\WordPressPluginDownloadController;
use App\Http\Controllers\WordPressPluginVersionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::view('/features', 'features')->name('features');
Route::view('/guide', 'guide')->name('guide');

// Always-fresh download of the latest packaged WP plugin — bypasses public/ caching.
Route::get('/wordpress/plugin.zip', WordPressPluginDownloadController::class)->name('wordpress.plugin.download');
Route::get('/wordpress/plugin/version', WordPressPluginVersionController::class)->name('wordpress.plugin.version');

// OAuth-style one-click link from the WordPress plugin.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/wordpress/connect', [WordPressConnectController::class, 'start'])->name('wordpress.connect.start');
    Route::post('/wordpress/connect', [WordPressConnectController::class, 'approve'])->name('wordpress.connect.approve');
});

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::view('/dashboard', 'dashboard')->middleware('feature:dashboard')->name('dashboard');
    Route::view('/keywords', 'keywords.index')->middleware('feature:keywords')->name('keywords.index');
    Route::get('/keywords/{query}', fn (string $query) => view('keywords.show', ['query' => $query]))
        ->middleware('feature:keywords')
        ->name('keywords.show')->where('query', '.*');
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

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/clients', [AdminClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [AdminClientController::class, 'store'])->name('clients.store');
    Route::put('/clients/{user}', [AdminClientController::class, 'update'])->name('clients.update');
    Route::post('/clients/{user}/impersonate', [ClientImpersonationController::class, 'start'])->name('clients.impersonate');

    Route::get('/activities', [AdminActivityController::class, 'index'])->name('activities.index');
    Route::get('/plugin-releases', [AdminPluginReleaseController::class, 'index'])->name('plugin-releases.index');
    Route::post('/plugin-releases', [AdminPluginReleaseController::class, 'store'])->name('plugin-releases.store');
    Route::post('/plugin-releases/{pluginRelease}/publish', [AdminPluginReleaseController::class, 'publish'])->name('plugin-releases.publish');
    Route::post('/plugin-releases/{pluginRelease}/rollback', [AdminPluginReleaseController::class, 'rollback'])->name('plugin-releases.rollback');
    Route::delete('/plugin-releases/{pluginRelease}', [AdminPluginReleaseController::class, 'destroy'])->name('plugin-releases.destroy');
    Route::get('/plugin-adoption', [AdminPluginAdoptionController::class, 'index'])->name('plugin-adoption.index');
});

Route::middleware('auth')->post('/admin/impersonation/stop', [ClientImpersonationController::class, 'stop'])->name('admin.impersonation.stop');
