<?php

namespace App\Jobs\Research;

use App\Models\Research\CompetitorScan;
use App\Models\Research\CompetitorScanKeyword;
use App\Models\Research\CompetitorTopic;
use App\Models\Research\Keyword;
use App\Services\Research\Niche\KeywordToNicheMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the scraper-extracted keyword universe into the niche
 * taxonomy. Runs after every successful scan: every keyword referenced
 * by the scan's competitor_topics or competitor_scan_keywords gets
 * passed through KeywordToNicheMapper, which upserts niche_keyword_map.
 *
 * Without this step the Topic Explorer / Topical Authority views can
 * never show anything for scraper-derived keywords — they'd be in the
 * `keywords` table but unattached to any niche.
 *
 * Idempotent — KeywordToNicheMapper short-circuits when a niche_keyword_map
 * row already exists for the keyword.
 */
class MapScanKeywordsToNichesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $scanId) {}

    public function handle(KeywordToNicheMapper $mapper): void
    {
        $scan = CompetitorScan::query()->find($this->scanId);
        if ($scan === null) {
            return;
        }

        $keywordIds = collect();

        // From competitor_topics — top keywords per discovered topic.
        CompetitorTopic::query()
            ->where('competitor_scan_id', $scan->id)
            ->get(['centroid_keyword_id', 'top_keyword_ids'])
            ->each(function (CompetitorTopic $topic) use ($keywordIds) {
                if ($topic->centroid_keyword_id) {
                    $keywordIds->push((int) $topic->centroid_keyword_id);
                }
                foreach ((array) $topic->top_keyword_ids as $kid) {
                    if (is_int($kid) || ctype_digit((string) $kid)) {
                        $keywordIds->push((int) $kid);
                    }
                }
            });

        // From competitor_scan_keywords — seed-keyword rankings.
        CompetitorScanKeyword::query()
            ->where('competitor_scan_id', $scan->id)
            ->pluck('keyword_id')
            ->each(fn ($kid) => $keywordIds->push((int) $kid));

        $unique = $keywordIds->unique()->filter()->values();
        if ($unique->isEmpty()) {
            return;
        }

        $keywords = Keyword::query()->whereIn('id', $unique)->get(['id', 'query']);

        $mapped = 0;
        foreach ($keywords as $keyword) {
            try {
                $matches = $mapper->map((string) $keyword->query, $keyword->id);
                if ($matches->isNotEmpty()) {
                    $mapped++;
                }
            } catch (\Throwable $e) {
                Log::warning('MapScanKeywordsToNichesJob: mapping failed', [
                    'keyword_id' => $keyword->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("MapScanKeywordsToNichesJob: mapped {$mapped} keyword(s) to niches for scan #{$scan->id}");
    }
}
