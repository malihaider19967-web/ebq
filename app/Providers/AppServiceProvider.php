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

        // LLM client — bind the interface to Mistral by default. Per-task
        // services that want a different provider for a specific endpoint
        // (e.g. Claude for copywriting in Phase 2) can take a concrete
        // class via constructor injection instead of the interface.
        $this->app->bind(\App\Services\Llm\LlmClient::class, function () {
            return new \App\Services\Llm\MistralClient(
                (string) config('services.mistral.key', ''),
                (string) config('services.mistral.model', 'mistral-small-latest'),
            );
        });
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
