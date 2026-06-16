<?php

namespace App\Jobs;

use App\Services\LighthouseClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Runs ONE strategy (mobile or desktop) of a PageSpeed test in the
 * background and stashes the parsed report in the cache for the Livewire
 * component to poll.
 *
 * Why per-strategy + async: a full mobile+desktop Lighthouse run over all
 * four categories can take well over a minute on a heavy site — long enough
 * to trip the queue worker's --timeout=90 AND Cloudflare's ~100s proxy
 * timeout (the 504 the user saw). Splitting into two short jobs keeps each
 * comfortably under the worker timeout, lets the two workers run them in
 * parallel, and moves the slow work off the web request entirely.
 */
class RunPageSpeedStrategy implements ShouldQueue
{
    use Queueable;

    // Below the worker's --timeout=90 so the job finishes (or the HTTP call
    // times out at 80s and we store a failure) rather than being killed.
    public int $timeout = 88;

    public int $tries = 1;

    public function __construct(
        public string $runId,
        public string $url,
        public string $strategy,
    ) {
        $this->onQueue(\App\Support\Queues::INTERACTIVE);
    }

    public function handle(LighthouseClient $lighthouse): void
    {
        $report = $lighthouse->fetchStrategyReport($this->url, $this->strategy);

        Cache::put($this->cacheKey(), $report ?? ['error' => true], now()->addMinutes(30));
    }

    /**
     * If the job blows up or times out, still record a result so the poller
     * stops waiting and can show a partial / failed report.
     */
    public function failed(?\Throwable $e): void
    {
        Cache::put($this->cacheKey(), ['error' => true], now()->addMinutes(30));
    }

    public function cacheKey(): string
    {
        return self::keyFor($this->runId, $this->strategy);
    }

    public static function keyFor(string $runId, string $strategy): string
    {
        return "pagespeed:{$runId}:{$strategy}";
    }
}
