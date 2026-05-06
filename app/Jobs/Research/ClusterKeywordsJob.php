<?php

namespace App\Jobs\Research;

use App\Models\Research\Keyword;
use App\Services\Research\ClusteringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reclusters a fixed set of keyword ids. Schedule-driven `ebq:research-
 * cluster-refresh` enqueues this job in batches keyed by country so
 * SERP-overlap is computed within comparable result sets.
 */
class ClusterKeywordsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 1;

    /** @param list<int> $keywordIds */
    public function __construct(public array $keywordIds) {}

    public function handle(ClusteringService $service): void
    {
        if ($this->keywordIds === []) {
            return;
        }

        $keywords = Keyword::query()->whereIn('id', $this->keywordIds)->get();
        if ($keywords->isEmpty()) {
            return;
        }

        $service->cluster($keywords);
    }
}
