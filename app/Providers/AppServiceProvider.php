<?php

namespace App\Providers;

use App\Listeners\RecordGrowthReportSent;
use App\Models\RankTrackingKeyword;
use App\Models\Website;
use App\Observers\RankTrackingKeywordObserver;
use App\Policies\WebsitePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once base_path('app/Support/helpers.php');

        $this->app->singleton(\App\Services\LanguageDetectorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Website::class, WebsitePolicy::class);

        RankTrackingKeyword::observe(RankTrackingKeywordObserver::class);

        Event::listen(MessageSent::class, RecordGrowthReportSent::class);

        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
