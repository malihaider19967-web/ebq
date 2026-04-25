<?php

namespace App\Jobs;

use App\Services\KeywordMetricsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetch Keywords Everywhere metrics for a batch of keywords and upsert them
 * into the `keyword_metrics` cache. KE caps payloads at 100 keywords per
 * request — the service layer chunks internally, so we can accept an
 * arbitrary-size list here.
 *
 * Job conventions (timeout/tries/backoff) mirror TrackKeywordRankJob.
 */
class FetchKeywordMetricsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 2;
    public int $backoff = 30;

    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public array $keywords,
        public string $country = 'global',
        public ?int $websiteId = null,
        public ?int $ownerUserId = null,
    ) {}

    public function handle(KeywordMetricsService $service): void
    {
        if ($this->keywords === []) {
            return;
        }

        $service->refresh($this->keywords, $this->country, $this->websiteId, $this->ownerUserId);
    }

    /**
     * Deduplicate identical batches while they're queued so rapid-fire
     * dispatches don't stack up. Include website_id so two different
     * websites asking for the same keyword each get their own credit hit.
     */
    public function uniqueId(): string
    {
        sort($this->keywords);

        return hash('sha256', $this->country.'|'.($this->websiteId ?? 0).'|'.implode("\n", $this->keywords));
    }

    public function uniqueFor(): int
    {
        return 300;
    }
}
