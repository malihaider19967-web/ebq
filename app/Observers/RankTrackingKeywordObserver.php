<?php

namespace App\Observers;

use App\Jobs\FetchKeywordMetricsJob;
use App\Models\RankTrackingKeyword;

/**
 * When a tracked keyword is created, queue a Keywords Everywhere lookup so the
 * UI has volume/CPC/competition on first render. Single global fetch per
 * keyword — per-country lookups are not a user-facing surface.
 */
class RankTrackingKeywordObserver
{
    public function created(RankTrackingKeyword $keyword): void
    {
        $text = trim((string) $keyword->keyword);
        if ($text === '') {
            return;
        }

        FetchKeywordMetricsJob::dispatch([$text], 'global');
    }
}
