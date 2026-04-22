<?php

namespace App\Jobs;

use App\Services\CompetitorBacklinkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetch and cache up to N backlinks for each competitor domain surfaced by an
 * audit's SERP benchmark. Fire-and-forget — the main audit completes first,
 * this fills the cache asynchronously so the UI reveals backlinks as they
 * land.
 *
 * Convention: matches TrackKeywordRankJob — tries=2, backoff=30s, 3-minute
 * timeout for the API call chain. Deduped via uniqueId so rapid-fire
 * dispatches for the same set don't stack.
 */
class FetchCompetitorBacklinks implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 2;
    public int $backoff = 30;

    /**
     * @param  list<string>  $domains  Normalized competitor domains.
     */
    public function __construct(public array $domains)
    {
    }

    public function handle(CompetitorBacklinkService $service): void
    {
        foreach ($this->domains as $domain) {
            $service->refresh((string) $domain);
        }
    }

    public function uniqueId(): string
    {
        $sorted = $this->domains;
        sort($sorted);

        return hash('sha256', implode("\n", $sorted));
    }

    public function uniqueFor(): int
    {
        return 300;
    }
}
