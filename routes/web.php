<?php

use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\GoogleCapController;
use App\Http\Controllers\PageAuditController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\ClientImpersonationController;
use App\Http\Controllers\Admin\PluginAdoptionController as AdminPluginAdoptionController;
use App\Http\Controllers\Admin\PluginReleaseController as AdminPluginReleaseController;
use App\Http\Controllers\Admin\UsageController as AdminUsageController;
use App\Http\Controllers\Admin\WebsiteFeatureController as AdminWebsiteFeatureController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\WordPressConnectController;
use App\Http\Controllers\WordPressPluginDownloadController;
use App\Http\Controllers\WordPressPluginVersionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::view('/features', 'features')->name('features');
Route::view('/pricing', 'pricing')->name('pricing');
Route::view('/contact', 'contact')->name('contact');
Route::view('/terms-conditions', 'legal.terms')->name('terms-conditions');
Route::view('/privacy-policy', 'legal.privacy')->name('privacy-policy');
Route::view('/refund-policy', 'legal.refund-policy')->name('refund-policy');
Route::view('/guide', 'guide')->name('guide');

// Always-fresh download of the latest packaged WP plugin — bypasses public/ caching.
Route::get('/wordpress/plugin.zip', WordPressPluginDownloadController::class)->name('wordpress.plugin.download');
Route::get('/wordpress/plugin/version', WordPressPluginVersionController::class)->name('wordpress.plugin.version');

// Public pricing API — anonymous, drives the WordPress plugin's setup
// wizard pricing step + any external integration that needs the plan
// list. No identifier captured (matches /wordpress/plugin/version).
Route::get('/api/v1/plans', [\App\Http\Controllers\Api\V1\PricingController::class, 'public'])
    ->name('api.v1.plans');

// Stripe webhook — extends Cashier's controller. CSRF-exempted in
// bootstrap/app.php. Stripe signs the request body; the controller
// verifies via STRIPE_WEBHOOK_SECRET so no extra auth needed.
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// Billing — Stripe Checkout + Customer Portal redirects. Auth required
// because we need the user context to resolve which Website is being
// billed. The /checkout endpoint mints a Stripe Hosted Checkout session
// and redirects; /success and /cancel handle the post-checkout return.
Route::middleware(['web', 'auth'])->group(function (): void {
    // Subscription management page — current plan, plan grid, cancel,
    // resume, recent invoices. Lives at /billing under sidebar nav.
    Route::get('/billing', [\App\Http\Controllers\BillingController::class, 'show'])
        ->name('billing.show');
    Route::post('/billing/swap', [\App\Http\Controllers\BillingController::class, 'swap'])
        ->name('billing.swap');
    Route::post('/billing/cancel', [\App\Http\Controllers\BillingController::class, 'cancelSubscription'])
        ->name('billing.cancel-subscription');
    Route::post('/billing/resume', [\App\Http\Controllers\BillingController::class, 'resume'])
        ->name('billing.resume');

    // Stripe Hosted Checkout flow — first-time purchase or post-cancel
    // re-subscribe. swap() is preferred for active subscribers.
    Route::get('/billing/checkout', [\App\Http\Controllers\BillingController::class, 'checkout'])
        ->name('billing.checkout');
    Route::get('/billing/success', [\App\Http\Controllers\BillingController::class, 'success'])
        ->name('billing.success');
    // Renamed from `billing.cancel` to free that name for the
    // subscription-cancel POST above. This handles "user backed out
    // of Stripe Checkout" only, not in-app cancellation.
    Route::get('/billing/cancel-checkout', [\App\Http\Controllers\BillingController::class, 'cancel'])
        ->name('billing.cancel-checkout');
    Route::get('/billing/portal', [\App\Http\Controllers\BillingController::class, 'portal'])
        ->name('billing.portal');
});

// OAuth-style one-click link from the WordPress plugin.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/wordpress/connect', [WordPressConnectController::class, 'start'])->name('wordpress.connect.start');
    Route::post('/wordpress/connect', [WordPressConnectController::class, 'approve'])->name('wordpress.connect.approve');
});

Route::middleware(['auth', 'verified', 'onboarded'])->group(function () {
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

    Route::middleware(['feature:research', 'research.rollout'])->prefix('research')->name('research.')->group(function () {
        Route::view('/', 'research.index')->name('index');
        Route::view('/keywords', 'research.keywords')->name('keywords');
        Route::view('/topics', 'research.topics')->name('topics');
        Route::view('/serp', 'research.serp')->name('serp');
        Route::view('/competitors', 'research.competitors')->name('competitors');
        Route::view('/content-gap', 'research.content-gap')->name('gap');
        Route::view('/briefs', 'research.briefs')->name('briefs');
        Route::get('/briefs/{brief}', fn (int $brief) => view('research.brief-show', ['briefId' => $brief]))
            ->whereNumber('brief')
            ->name('briefs.show');
        Route::view('/topical-authority', 'research.topical-authority')->name('authority');
        Route::view('/coverage', 'research.coverage')->name('coverage');
        Route::view('/internal-links', 'research.internal-links')->name('internal-links');
        Route::view('/opportunities', 'research.opportunities')->name('opportunities');
        Route::view('/alerts', 'research.alerts')->name('alerts');
        Route::view('/reverse', 'research.reverse')->name('reverse');
        Route::view('/performance', 'research.performance')->name('performance');
    });
});

Route::middleware(['auth', 'verified', 'throttle:oauth'])->group(function () {
    Route::view('/onboarding', 'onboarding.index')->name('onboarding');
    Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
});

// Google Cross-Account Protection (RISC/CAP) receiver endpoint.
Route::post('/auth/google/cap/events', GoogleCapController::class)
    ->middleware(['throttle:oauth'])
    ->name('google.cap.events');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/clients', [AdminClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [AdminClientController::class, 'store'])->name('clients.store');
    Route::put('/clients/{user}', [AdminClientController::class, 'update'])->name('clients.update');
    Route::post('/clients/{user}/impersonate', [ClientImpersonationController::class, 'start'])->name('clients.impersonate');

    Route::get('/activities', [AdminActivityController::class, 'index'])->name('activities.index');
    Route::get('/usage', [AdminUsageController::class, 'index'])->name('usage.index');
    Route::get('/plugin-releases', [AdminPluginReleaseController::class, 'index'])->name('plugin-releases.index');
    Route::post('/plugin-releases', [AdminPluginReleaseController::class, 'store'])->name('plugin-releases.store');
    Route::post('/plugin-releases/{pluginRelease}/publish', [AdminPluginReleaseController::class, 'publish'])->name('plugin-releases.publish');
    Route::post('/plugin-releases/{pluginRelease}/zip', [AdminPluginReleaseController::class, 'uploadZip'])->name('plugin-releases.upload-zip');
    Route::post('/plugin-releases/{pluginRelease}/rollback', [AdminPluginReleaseController::class, 'rollback'])->name('plugin-releases.rollback');
    Route::delete('/plugin-releases/{pluginRelease}', [AdminPluginReleaseController::class, 'destroy'])->name('plugin-releases.destroy');
    Route::get('/plugin-adoption', [AdminPluginAdoptionController::class, 'index'])->name('plugin-adoption.index');

    // Per-website WordPress-plugin feature toggles. Admin enables/disables
    // value-add features (chatbot, AI writer, etc.) on a per-site basis.
    // Core SEO output is never gated server-side, so disabling here can
    // never de-index a customer's site.
    Route::get('/website-features', [AdminWebsiteFeatureController::class, 'index'])
        ->name('website-features.index');
    // Global master kill-switch — overrides per-site flags. Lives at a
    // distinct path so the route model binding for `{website}` below
    // doesn't claim the literal "global" segment.
    Route::put('/website-features/global', [AdminWebsiteFeatureController::class, 'globalUpdate'])
        ->name('website-features.global-update');
    Route::put('/website-features/{website}', [AdminWebsiteFeatureController::class, 'update'])
        ->name('website-features.update');

    // Per-website subscription overview — sits as a tab on the
    // WordPress Plugin master page. Read-only in v1.
    Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing.index');

    // Plan management — drives the marketing /pricing page, the public
    // /api/v1/plans endpoint, and the Stripe checkout flow. Editable
    // per-row: name, pricing, Stripe price IDs, trial days, features,
    // active/highlighted state.
    Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [AdminPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

    Route::prefix('research')->name('research.')->group(function (): void {
        Route::get('/niche-candidates', [\App\Http\Controllers\Admin\NicheCandidateController::class, 'index'])
            ->name('niche-candidates.index');
        Route::post('/niche-candidates/{niche}/approve', [\App\Http\Controllers\Admin\NicheCandidateController::class, 'approve'])
            ->whereNumber('niche')->name('niche-candidates.approve');
        Route::delete('/niche-candidates/{niche}', [\App\Http\Controllers\Admin\NicheCandidateController::class, 'destroy'])
            ->whereNumber('niche')->name('niche-candidates.destroy');
    });
});

Route::middleware('auth')->post('/admin/impersonation/stop', [ClientImpersonationController::class, 'stop'])->name('admin.impersonation.stop');
