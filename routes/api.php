<?php

use App\Http\Controllers\Api\V1\PluginInsightsController;
use App\Http\Controllers\Api\V1\WebsiteVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Verification is session-auth — called from the EBQ UI, not the plugin.
    Route::middleware(['web', 'auth'])->group(function (): void {
        Route::post('/verify/challenge', [WebsiteVerificationController::class, 'challenge'])
            ->name('api.v1.verify.challenge');
        Route::post('/verify/confirm', [WebsiteVerificationController::class, 'confirm'])
            ->name('api.v1.verify.confirm');
    });

    // Plugin-facing endpoints — bearer token issued by /verify/confirm.
    Route::middleware(['website.api:read:insights', 'throttle:60,1'])->group(function (): void {
        Route::get('/posts/{externalPostId}/insights', [PluginInsightsController::class, 'showPost'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.show');
        Route::get('/posts', [PluginInsightsController::class, 'indexPosts'])
            ->name('api.v1.posts.index');
        Route::get('/dashboard', [PluginInsightsController::class, 'dashboard'])
            ->name('api.v1.dashboard');
        Route::get('/reports/iframe-url', [PluginInsightsController::class, 'iframeUrl'])
            ->name('api.v1.reports.iframe');
    });
});
