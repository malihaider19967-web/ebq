<?php

namespace App\Services;

use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use App\Models\Website;
use Illuminate\Support\Carbon;

/**
 * Surfaces the SERP-feature presence timeline for tracked keywords.
 *
 * The Serper-driven rank tracker (`RankTrackingService`) already captures
 * `serp_features` (answer box, people-also-ask, image pack, sitelinks,
 * etc.) on every snapshot. This service projects that into a
 * UI-friendly time series:
 *
 *   - per-keyword: which features appeared on which days
 *   - per-keyword: do you OWN any features? (your URL inside the answer
 *     box / sitelinks / image pack)
 *   - aggregate: % of tracked keywords showing answer-box / PAA / image
 *     pack today vs 30/90 days ago
 *
 * Phase 3 #6 — extends Rank Tracker with feature-presence intelligence
 * agencies live for. Pure DB read; no external API calls here (the data
 * is already accumulated by the daily rank-tracking cron).
 *
 * MOAT: timeline depth compounds with site age. A site with 90 days of
 * snapshots can show feature-volatility trends that a fresh competitor
 * tool can't reproduce no matter how many credits they spend.
 */
class SerpFeatureTrackerService
{
    public const FEATURE_KEYS = [
        'answer_box',
        'people_also_ask',
        'image_pack',
        'sitelinks',
        'video',
        'top_stories',
        'knowledge_panel',
        'shopping',
    ];

    /**
     * @return array{
     *   keywords: list<array{
     *     id: int,
     *     keyword: string,
     *     country: string,
     *     features_today: list<string>,
     *     features_owned: list<string>,
     *     timeline: list<array{date: string, features: list<string>}>,
     *   }>,
     *   summary: array{
     *     total: int,
     *     with_answer_box: int,
     *     with_paa: int,
     *     with_image_pack: int,
     *     with_any_feature: int,
     *   }
     * }
     */
    public function forWebsite(Website $website, int $days = 30): array
    {
        $cutoff = Carbon::now()->subDays(max(1, min(365, $days)));

        $keywords = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->where('is_active', true)
            ->orderByDesc('last_checked_at')
            ->limit(500)
            ->get();

        if ($keywords->isEmpty()) {
            return [
                'keywords' => [],
                'summary' => [
                    'total' => 0,
                    'with_answer_box' => 0,
                    'with_paa' => 0,
                    'with_image_pack' => 0,
                    'with_any_feature' => 0,
                ],
            ];
        }

        $snapshots = RankTrackingSnapshot::query()
            ->whereIn('rank_tracking_keyword_id', $keywords->pluck('id'))
            ->where('checked_at', '>=', $cutoff)
            ->orderBy('checked_at')
            ->get()
            ->groupBy('rank_tracking_keyword_id');

        $rows = [];
        $summary = [
            'total' => $keywords->count(),
            'with_answer_box' => 0,
            'with_paa' => 0,
            'with_image_pack' => 0,
            'with_any_feature' => 0,
        ];

        foreach ($keywords as $kw) {
            $kwSnaps = $snapshots->get($kw->id, collect());
            $latest = $kwSnaps->last();

            $featuresToday = $this->extractFeatures($latest?->serp_features ?? null);
            $featuresOwned = $this->extractOwnedFeatures($latest?->serp_features ?? null, $website);

            $timeline = [];
            $byDate = [];
            foreach ($kwSnaps as $s) {
                $d = $s->checked_at?->toDateString();
                if ($d === null) continue;
                $byDate[$d] = $this->extractFeatures($s->serp_features);
            }
            foreach ($byDate as $d => $features) {
                $timeline[] = ['date' => $d, 'features' => $features];
            }

            if (in_array('answer_box', $featuresToday, true))      $summary['with_answer_box']++;
            if (in_array('people_also_ask', $featuresToday, true)) $summary['with_paa']++;
            if (in_array('image_pack', $featuresToday, true))      $summary['with_image_pack']++;
            if ($featuresToday !== [])                              $summary['with_any_feature']++;

            $rows[] = [
                'id' => $kw->id,
                'keyword' => (string) $kw->keyword,
                'country' => (string) $kw->country,
                'features_today' => $featuresToday,
                'features_owned' => $featuresOwned,
                'timeline' => $timeline,
            ];
        }

        return ['keywords' => $rows, 'summary' => $summary];
    }

    /**
     * @param  mixed  $raw  The serp_features JSON column (array | null)
     * @return list<string>
     */
    private function extractFeatures($raw): array
    {
        if (! is_array($raw)) return [];
        $out = [];
        foreach (self::FEATURE_KEYS as $k) {
            $present = $raw[$k] ?? null;
            // serp_features may store either a bool (present/not) or an
            // array (the actual block). Either form means "feature appeared".
            if (is_bool($present)) {
                if ($present) $out[] = $k;
            } elseif (is_array($present) && $present !== []) {
                $out[] = $k;
            }
        }
        return $out;
    }

    /**
     * Did the website's own domain appear inside any feature block (e.g.
     * the answer box quote, a sitelinks set, an image pack thumbnail)?
     * That's the highest-value SERP-features signal — owning a feature
     * is worth more than ranking #1 organically.
     *
     * @param  mixed  $raw
     * @return list<string>
     */
    private function extractOwnedFeatures($raw, Website $website): array
    {
        if (! is_array($raw)) return [];
        $domain = strtolower(preg_replace('/^www\./', '', (string) $website->domain) ?: '');
        if ($domain === '') return [];

        $owned = [];
        foreach (self::FEATURE_KEYS as $k) {
            $block = $raw[$k] ?? null;
            if (! is_array($block) || $block === []) continue;
            // Walk the block and check any string value that looks like a
            // URL — if its host matches our domain, we own this feature.
            if ($this->blockContainsDomain($block, $domain)) {
                $owned[] = $k;
            }
        }
        return $owned;
    }

    private function blockContainsDomain(array $block, string $domain): bool
    {
        $iter = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($block));
        foreach ($iter as $value) {
            if (! is_string($value) || $value === '') continue;
            $host = parse_url($value, PHP_URL_HOST);
            if (! is_string($host) || $host === '') continue;
            $host = strtolower(preg_replace('/^www\./', '', $host) ?: $host);
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }
}
