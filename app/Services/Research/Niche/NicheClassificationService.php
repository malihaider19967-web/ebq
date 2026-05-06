<?php

namespace App\Services\Research\Niche;

use App\Models\Research\SerpResult;
use App\Models\Research\WebsitePage;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes a multi-label, weighted niche assignment for a Website. Three
 * signals (matching §3.2 of docs/research-section-plan.md):
 *
 *   GSC top queries (impressions-weighted) ........ 0.5
 *   Homepage + blog headings/H1/H2 ................ 0.3
 *   Reverse-research ranking keywords ............. 0.2
 *
 * Output is normalised so weights sum to 1.0, with primary = highest and
 * secondaries those above a 0.05 threshold (capped at 5).
 */
class NicheClassificationService
{
    private const SIGNAL_WEIGHTS = [
        'gsc' => 0.5,
        'content' => 0.3,
        'reverse' => 0.2,
    ];

    private const SECONDARY_THRESHOLD = 0.05;
    private const MAX_SECONDARIES = 5;

    public function __construct(
        private readonly KeywordToNicheMapper $mapper,
    ) {}

    /**
     * @return Collection<int, array{niche_id:int, weight:float, is_primary:bool, confidence:float}>
     */
    public function classify(Website $website): Collection
    {
        $scores = [];

        foreach ($this->gscSignal($website) as $nicheId => $score) {
            $scores[$nicheId] = ($scores[$nicheId] ?? 0) + $score * self::SIGNAL_WEIGHTS['gsc'];
        }

        foreach ($this->contentSignal($website) as $nicheId => $score) {
            $scores[$nicheId] = ($scores[$nicheId] ?? 0) + $score * self::SIGNAL_WEIGHTS['content'];
        }

        foreach ($this->reverseSignal($website) as $nicheId => $score) {
            $scores[$nicheId] = ($scores[$nicheId] ?? 0) + $score * self::SIGNAL_WEIGHTS['reverse'];
        }

        if ($scores === []) {
            return collect();
        }

        $sum = array_sum($scores);
        if ($sum <= 0) {
            return collect();
        }

        arsort($scores);
        $normalized = array_map(fn ($v) => round($v / $sum, 4), $scores);

        $output = collect();
        $isFirst = true;
        $kept = 0;
        foreach ($normalized as $nicheId => $weight) {
            if (! $isFirst && ($weight < self::SECONDARY_THRESHOLD || $kept >= self::MAX_SECONDARIES + 1)) {
                break;
            }
            $output->push([
                'niche_id' => (int) $nicheId,
                'weight' => $weight,
                'is_primary' => $isFirst,
                'confidence' => $weight,
            ]);
            $isFirst = false;
            $kept++;
        }

        return $output;
    }

    /**
     * Persist classification results into website_niche_map. Source defaults
     * to 'auto'; pass 'hybrid' or 'user' from the onboarding step.
     *
     * @param  Collection<int, array{niche_id:int, weight:float, is_primary:bool, confidence:float}>  $assignments
     */
    public function persist(Website $website, Collection $assignments, string $source = 'auto'): void
    {
        $now = Carbon::now();
        $payload = $assignments->mapWithKeys(fn ($row) => [
            $row['niche_id'] => [
                'weight' => $row['weight'],
                'is_primary' => $row['is_primary'],
                'source' => $source,
                'confidence' => $row['confidence'],
                'last_classified_at' => $now,
            ],
        ])->all();

        $website->niches()->sync($payload);
    }

    /** @return array<int, float> niche_id => raw score */
    private function gscSignal(Website $website): array
    {
        $rows = SearchConsoleData::query()
            ->select('query', 'country')
            ->selectRaw('SUM(impressions) as total_impressions')
            ->where('website_id', $website->id)
            ->where('query', '!=', '')
            ->whereDate('date', '>=', Carbon::now()->subDays(90)->toDateString())
            ->groupBy('query', 'country')
            ->orderByDesc('total_impressions')
            ->limit(200)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $maxImp = max(1, (int) $rows->max('total_impressions'));
        $scores = [];
        foreach ($rows as $row) {
            $weight = log(1 + (int) $row->total_impressions) / log(1 + $maxImp);
            foreach ($this->mapper->map((string) $row->query) as $match) {
                $scores[$match['niche_id']] = ($scores[$match['niche_id']] ?? 0)
                    + $match['relevance_score'] * $weight;
            }
        }

        return $scores;
    }

    /** @return array<int, float> niche_id => raw score */
    private function contentSignal(Website $website): array
    {
        $pages = WebsitePage::query()
            ->where('website_id', $website->id)
            ->whereNotNull('headings_json')
            ->limit(50)
            ->get();

        if ($pages->isEmpty()) {
            // Fall back to whatever titles exist when no headings have been crawled yet.
            $pages = WebsitePage::query()
                ->where('website_id', $website->id)
                ->whereNotNull('title')
                ->limit(50)
                ->get();
        }

        if ($pages->isEmpty()) {
            return [];
        }

        $scores = [];
        foreach ($pages as $page) {
            $texts = [];
            if (is_string($page->title) && $page->title !== '') {
                $texts[] = $page->title;
            }
            if (is_array($page->headings_json)) {
                foreach ($page->headings_json as $heading) {
                    if (is_string($heading)) {
                        $texts[] = $heading;
                    } elseif (is_array($heading) && isset($heading['text'])) {
                        $texts[] = (string) $heading['text'];
                    }
                }
            }

            foreach ($texts as $text) {
                foreach ($this->mapper->map($text) as $match) {
                    $scores[$match['niche_id']] = ($scores[$match['niche_id']] ?? 0)
                        + $match['relevance_score'];
                }
            }
        }

        return $scores;
    }

    /** @return array<int, float> niche_id => raw score */
    private function reverseSignal(Website $website): array
    {
        $domain = $this->extractDomain($website);
        if ($domain === null) {
            return [];
        }

        $keywordIds = SerpResult::query()
            ->where('domain', $domain)
            ->where('rank', '<=', 20)
            ->whereIn('snapshot_id', function ($sub) {
                $sub->select('id')->from('serp_snapshots');
            })
            ->with('snapshot:id,keyword_id')
            ->limit(500)
            ->get()
            ->pluck('snapshot.keyword_id')
            ->filter()
            ->unique()
            ->all();

        if ($keywordIds === []) {
            return [];
        }

        $scores = [];
        foreach ($keywordIds as $keywordId) {
            $keyword = \App\Models\Research\Keyword::query()->find($keywordId);
            if ($keyword === null) {
                continue;
            }
            foreach ($this->mapper->map($keyword->query, $keyword->id) as $match) {
                $scores[$match['niche_id']] = ($scores[$match['niche_id']] ?? 0)
                    + $match['relevance_score'];
            }
        }

        return $scores;
    }

    private function extractDomain(Website $website): ?string
    {
        $raw = $website->domain ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $host = parse_url($raw, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $host = $raw;
        }

        return mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host);
    }
}
