<?php

use App\Http\Controllers\Api\V1\PluginHqController;
use App\Http\Controllers\Api\V1\PluginInsightsController;
use App\Http\Controllers\Api\V1\PluginHeartbeatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Plugin-facing endpoints — bearer token issued by the /wordpress/connect flow.
    Route::middleware(['website.api:read:insights', 'throttle:60,1'])->group(function (): void {
        Route::get('/posts/{externalPostId}/insights', [PluginInsightsController::class, 'showPost'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.show');
        Route::get('/posts/{externalPostId}/focus-keyword-suggestions', [PluginInsightsController::class, 'focusKeywordSuggestions'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.focus-keyword-suggestions');
        Route::get('/posts/{externalPostId}/serp-preview', [PluginInsightsController::class, 'serpPreview'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.serp-preview');
        Route::get('/posts/{externalPostId}/internal-link-suggestions', [PluginInsightsController::class, 'internalLinkSuggestions'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.internal-link-suggestions');
        Route::get('/posts/{externalPostId}/related-keywords', [PluginInsightsController::class, 'relatedKeywords'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.related-keywords');
        Route::get('/posts', [PluginInsightsController::class, 'indexPosts'])
            ->name('api.v1.posts.index');
        Route::get('/dashboard', [PluginInsightsController::class, 'dashboard'])
            ->name('api.v1.dashboard');
        Route::get('/reports/iframe-url', [PluginInsightsController::class, 'iframeUrl'])
            ->name('api.v1.reports.iframe');
        Route::post('/plugin/heartbeat', PluginHeartbeatController::class)
            ->name('api.v1.plugin.heartbeat');

        // EBQ HQ — top-level WP-admin analytics dashboards.
        Route::prefix('hq')->name('api.v1.hq.')->group(function (): void {
            Route::get('/overview', [PluginHqController::class, 'overview'])->name('overview');
            Route::get('/performance', [PluginHqController::class, 'performance'])->name('performance');
            Route::get('/keywords', [PluginHqController::class, 'keywords'])->name('keywords');
            Route::post('/keywords', [PluginHqController::class, 'storeKeyword'])->name('keywords.store');
            Route::get('/keywords/candidates', [PluginHqController::class, 'keywordCandidates'])->name('keywords.candidates');
            Route::patch('/keywords/{id}', [PluginHqController::class, 'updateKeyword'])
                ->whereNumber('id')->name('keywords.update');
            Route::delete('/keywords/{id}', [PluginHqController::class, 'deleteKeyword'])
                ->whereNumber('id')->name('keywords.delete');
            Route::post('/keywords/{id}/recheck', [PluginHqController::class, 'recheckKeyword'])
                ->whereNumber('id')->name('keywords.recheck');
            Route::get('/keywords/{id}/history', [PluginHqController::class, 'keywordHistory'])
                ->whereNumber('id')
                ->name('keywords.history');
            Route::get('/gsc-keywords', [PluginHqController::class, 'gscKeywords'])->name('gsc-keywords');
            Route::get('/pages', [PluginHqController::class, 'pages'])->name('pages');
            Route::get('/index-status', [PluginHqController::class, 'indexStatus'])->name('index-status');
            Route::get('/insights/{type}', [PluginHqController::class, 'insights'])
                ->where('type', '[a-z_]+')
                ->name('insights');
        });
    });
});
