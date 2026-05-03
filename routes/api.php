<?php

use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\PluginHqController;
use App\Http\Controllers\Api\V1\PluginInsightsController;
use App\Http\Controllers\Api\V1\PluginHeartbeatController;
use App\Http\Controllers\Api\V1\WriterProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Plugin-facing endpoints — bearer token issued by the /wordpress/connect flow.
    // `website.features` runs after the controller and stamps the resolved
    // feature-flag map onto every JSON response, so the WP plugin's passive
    // sync (`EBQ_Feature_Flags::store` from `handle_response`) refreshes its
    // local cache on every API call.
    Route::middleware(['website.api:read:insights', 'website.features', 'throttle:60,1'])->group(function (): void {
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
        Route::get('/posts/{externalPostId}/seo-score', [PluginInsightsController::class, 'seoScore'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.seo-score');
        Route::post('/posts/{externalPostId}/topical-gaps', [PluginInsightsController::class, 'topicalGaps'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.topical-gaps');
        Route::post('/posts/{externalPostId}/rewrite-snippet', [PluginInsightsController::class, 'rewriteSnippet'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.rewrite-snippet');
        Route::get('/posts/rewrite-intents', [PluginInsightsController::class, 'rewriteIntents'])
            ->name('api.v1.posts.rewrite-intents');
        Route::post('/posts/{externalPostId}/content-brief', [PluginInsightsController::class, 'contentBrief'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.content-brief');
        Route::post('/posts/{externalPostId}/ai-writer', [PluginInsightsController::class, 'aiWriter'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.ai-writer');
        Route::get('/posts/{externalPostId}/research', [PluginInsightsController::class, 'research'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.research');
        Route::post('/posts/{externalPostId}/ai-writer/plan', [PluginInsightsController::class, 'aiWriterPlan'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.ai-writer.plan');
        Route::post('/posts/{externalPostId}/ai-block', [PluginInsightsController::class, 'aiBlock'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.ai-block');
        Route::post('/posts/{externalPostId}/chat', [PluginInsightsController::class, 'aiChat'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.chat');
        Route::get('/posts/{externalPostId}/entity-coverage', [PluginInsightsController::class, 'entityCoverage'])
            ->where('externalPostId', '[A-Za-z0-9_\-\.]+')
            ->name('api.v1.posts.entity-coverage');
        Route::post('/posts/report-404s', [PluginInsightsController::class, 'report404s'])
            ->name('api.v1.posts.report-404s');
        Route::get('/redirect-suggestions', [PluginInsightsController::class, 'redirectSuggestions'])
            ->name('api.v1.redirect-suggestions.index');
        Route::post('/redirect-suggestions/{id}/decide', [PluginInsightsController::class, 'decideRedirectSuggestion'])
            ->where('id', '[0-9]+')
            ->name('api.v1.redirect-suggestions.decide');
        Route::get('/posts', [PluginInsightsController::class, 'indexPosts'])
            ->name('api.v1.posts.index');
        Route::get('/dashboard', [PluginInsightsController::class, 'dashboard'])
            ->name('api.v1.dashboard');
        Route::get('/reports/iframe-url', [PluginInsightsController::class, 'iframeUrl'])
            ->name('api.v1.reports.iframe');
        Route::post('/plugin/heartbeat', PluginHeartbeatController::class)
            ->name('api.v1.plugin.heartbeat');

        // Per-website feature toggle map. The WordPress plugin reads this
        // to enable/disable value-add features site-by-site (Rank Assist,
        // AI Writer, AI inline toolbar, live audit, EBQ HQ, redirects,
        // dashboard widget, posts-list column). Core SEO output is never
        // gated server-side. Default-ON when a website has no overrides.
        Route::get('/website-features', [PluginInsightsController::class, 'websiteFeatures'])
            ->name('api.v1.website-features');

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
            Route::post('/index-status/submit', [PluginHqController::class, 'indexStatusSubmit'])->name('index-status.submit');
            Route::get('/insights/{type}', [PluginHqController::class, 'insights'])
                ->where('type', '[a-z_]+')
                ->name('insights');

            // Phase 3 — network-effect features
            Route::get('/serp-features', [PluginHqController::class, 'serpFeatures'])->name('serp-features');
            Route::post('/backlink-prospects', [PluginHqController::class, 'backlinkProspects'])->name('backlink-prospects');
            Route::post('/backlink-prospects/draft', [PluginHqController::class, 'backlinkOutreachDraft'])->name('backlink-prospects.draft');
            Route::get('/outreach-prospects', [PluginHqController::class, 'outreachProspectsList'])->name('outreach-prospects.index');
            Route::post('/outreach-prospects/auto-discover', [PluginHqController::class, 'outreachProspectsAutoDiscover'])->name('outreach-prospects.auto-discover');
            Route::post('/outreach-prospects/{id}', [PluginHqController::class, 'outreachProspectsUpdate'])
                ->whereNumber('id')
                ->name('outreach-prospects.update');
            Route::get('/benchmarks', [PluginHqController::class, 'crossSiteBenchmarks'])->name('benchmarks');
            Route::get('/topical-authority', [PluginHqController::class, 'topicalAuthority'])->name('topical-authority');

            // AI Writer wizard — multi-step project lifecycle. Replaces
            // the stateless /posts/{id}/ai-writer flow for HQ-side drafts.
            // Persistence + Serper image search + chat-driven brief edits
            // + EBQ Content Credit accounting all live behind these routes.
            Route::get('/writer-projects', [WriterProjectController::class, 'index'])->name('writer-projects.index');
            Route::post('/writer-projects', [WriterProjectController::class, 'store'])->name('writer-projects.store');
            Route::get('/writer-projects/{externalId}', [WriterProjectController::class, 'show'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.show');
            Route::patch('/writer-projects/{externalId}', [WriterProjectController::class, 'update'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.update');
            Route::delete('/writer-projects/{externalId}', [WriterProjectController::class, 'destroy'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.destroy');
            Route::post('/writer-projects/{externalId}/brief', [WriterProjectController::class, 'generateBrief'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.brief');
            Route::post('/writer-projects/{externalId}/brief/chat', [WriterProjectController::class, 'briefChat'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.brief.chat');
            Route::post('/writer-projects/{externalId}/images/search', [WriterProjectController::class, 'searchImages'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.images.search');
            Route::post('/writer-projects/{externalId}/generate', [WriterProjectController::class, 'generate'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.generate');
            Route::get('/writer-projects/{externalId}/credits', [WriterProjectController::class, 'credits'])
                ->where('externalId', '[A-Za-z0-9\-]+')
                ->name('writer-projects.credits');

            // AI Studio — registry-driven tool catalog. Single execution
            // path for every AI feature (research, writing, improvement,
            // marketing, eCommerce, media, utilities).
            Route::get('/ai/tools', [AiToolController::class, 'indexTools'])->name('ai.tools.index');
            Route::get('/ai/tools/{toolId}', [AiToolController::class, 'showTool'])
                ->where('toolId', '[a-z0-9\-]+')
                ->name('ai.tools.show');
            Route::post('/ai/tools/{toolId}/run', [AiToolController::class, 'runTool'])
                ->where('toolId', '[a-z0-9\-]+')
                ->name('ai.tools.run');
            Route::get('/ai/brand-voice', [AiToolController::class, 'brandVoiceShow'])->name('ai.brand-voice.show');
            Route::put('/ai/brand-voice', [AiToolController::class, 'brandVoiceUpdate'])->name('ai.brand-voice.update');
            Route::delete('/ai/brand-voice', [AiToolController::class, 'brandVoiceDestroy'])->name('ai.brand-voice.destroy');
        });
    });
});
