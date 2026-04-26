<?php

namespace App\Services;

use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 3 #4 — Topical authority map.
 *
 * Approach (pragmatic, no embedding pipeline):
 *   GSC queries are already pre-clustered for free by Google's NLU —
 *   queries that rank for the SAME page tend to share semantic topic.
 *   So we cluster on co-occurrence: queries that share at least one
 *   significant token AND rank for at least one page in common.
 *
 *   Authority score per cluster = blend of (a) avg position across the
 *   cluster's queries, (b) total clicks, (c) impression breadth, (d) #
 *   ranking pages. Higher numbers = stronger topical depth.
 *
 *   This is ~80% as good as a real embedding-based clustering for the
 *   reporting use-case ("you have authority on cluster X but a gap on
 *   cluster Y") and avoids the entire embedding-storage + nightly
 *   batch-job infrastructure. A future embedding pass can replace the
 *   token-overlap step transparently — output schema stays the same.
 *
 * MOAT note
 * ─────────
 * Computation lives on EBQ; the inputs (GSC join + cross-page co-occurrence
 * math) require server-side data the WP plugin can't see. Network effect
 * latent: when we eventually compute the same clusters across all sites,
 * we can show "cluster X strength: you 64 vs industry avg 42" — that's
 * the thing single-site competitors can't match.
 */
class TopicalAuthorityService
{
    private const CACHE_TTL_HOURS = 24;
    private const MIN_QUERY_IMPRESSIONS = 5;
    private const MAX_CLUSTERS = 30;
    private const MIN_TOKEN_LENGTH = 3;

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'a', 'an', 'of', 'to', 'in', 'on',
        'is', 'are', 'or', 'be', 'at', 'by', 'as', 'it', 'that', 'this',
        'how', 'what', 'why', 'when', 'where', 'who', 'can', 'do', 'does',
        'will', 'would', 'should', 'has', 'have', 'had', 'was', 'were',
        'not', 'but', 'from', 'about', 'into', 'over', 'under', 'than',
        'then', 'so', 'if', 'no', 'yes', 'all', 'any', 'some', 'more',
        'most', 'other', 'one', 'two', 'free', 'best', 'top', 'new',
    ];

    /**
     * @return array{
     *   ok: bool,
     *   reason?: string,
     *   clusters: list<array{
     *     id: string,
     *     label: string,
     *     queries: list<string>,
     *     pages: list<string>,
     *     authority_score: int,
     *     avg_position: float,
     *     total_clicks: int,
     *     total_impressions: int,
     *   }>,
     *   gaps: list<array{label: string, suggested_action: string}>,
     * }
     */
    public function map(Website $website): array
    {
        $cacheKey = sprintf('ebq_topical_authority:%d', $website->id);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz);
        $start = $end->copy()->subDays(89); // 90-day window for stable clustering

        // Pull aggregated query×page rows (rather than daily granularity).
        $rows = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('query', '!=', '')
            ->where('page', '!=', '')
            ->selectRaw('LOWER(query) AS q, page AS p, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS pos')
            ->groupBy('q', 'p')
            ->havingRaw('SUM(impressions) >= ?', [self::MIN_QUERY_IMPRESSIONS])
            ->orderByDesc('impressions')
            ->limit(2000)
            ->get();

        if ($rows->isEmpty()) {
            $result = ['ok' => false, 'reason' => 'no_gsc_data', 'clusters' => [], 'gaps' => []];
            Cache::put($cacheKey, $result, Carbon::now()->addHours(self::CACHE_TTL_HOURS));
            return $result;
        }

        // Build query → tokens, query → pages, query → metrics maps.
        $queryTokens = [];
        $queryPages = [];
        $queryStats = [];
        foreach ($rows as $r) {
            $q = (string) $r->q;
            $tokens = $this->significantTokens($q);
            if ($tokens === []) continue;

            $queryTokens[$q] = $tokens;
            $queryPages[$q][(string) $r->p] = true;
            if (! isset($queryStats[$q])) {
                $queryStats[$q] = ['clicks' => 0, 'impressions' => 0, 'positions' => []];
            }
            $queryStats[$q]['clicks']      += (int) $r->clicks;
            $queryStats[$q]['impressions'] += (int) $r->impressions;
            $queryStats[$q]['positions'][]  = (float) $r->pos;
        }

        // Inverted index: token → list of queries containing it.
        $tokenIndex = [];
        foreach ($queryTokens as $q => $toks) {
            foreach ($toks as $t) {
                $tokenIndex[$t][] = $q;
            }
        }

        // Cluster: union-find queries that share ANY non-trivial token AND
        // share at least one ranking page. Two-signal join filters out the
        // generic-token false positives ("a guide to X" / "a guide to Y").
        $parent = [];
        foreach (array_keys($queryTokens) as $q) {
            $parent[$q] = $q;
        }
        $find = function (string $x) use (&$parent, &$find): string {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        $union = function (string $a, string $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) $parent[$rb] = $ra;
        };

        foreach ($tokenIndex as $token => $queries) {
            // Skip ultra-frequent tokens (acts like a stop-token at scale).
            if (count($queries) > 200) continue;
            $primary = $queries[0];
            for ($i = 1; $i < count($queries); $i++) {
                $other = $queries[$i];
                // Page co-occurrence check.
                if (! empty(array_intersect_key(
                    $queryPages[$primary] ?? [],
                    $queryPages[$other]   ?? [],
                ))) {
                    $union($primary, $other);
                }
            }
        }

        // Materialize clusters.
        $groups = [];
        foreach (array_keys($parent) as $q) {
            $root = $find($q);
            $groups[$root][] = $q;
        }

        $clusters = [];
        foreach ($groups as $rootQuery => $queries) {
            if (count($queries) < 2) continue; // single-query clusters are noise

            $totalClicks = 0;
            $totalImps = 0;
            $allPositions = [];
            $allPages = [];
            $tokenFreq = [];
            foreach ($queries as $q) {
                $stats = $queryStats[$q] ?? null;
                if ($stats === null) continue;
                $totalClicks += $stats['clicks'];
                $totalImps   += $stats['impressions'];
                $allPositions = array_merge($allPositions, $stats['positions']);
                $allPages += $queryPages[$q] ?? [];
                foreach ($queryTokens[$q] ?? [] as $t) {
                    $tokenFreq[$t] = ($tokenFreq[$t] ?? 0) + 1;
                }
            }
            if ($totalImps < 20) continue; // too thin to be meaningful

            $avgPos = $allPositions !== [] ? array_sum($allPositions) / count($allPositions) : 100.0;
            arsort($tokenFreq);
            $label = implode(' ', array_slice(array_keys($tokenFreq), 0, 3));

            $clusters[] = [
                'id' => substr(hash('xxh3', $rootQuery), 0, 12),
                'label' => $label,
                'queries' => array_slice($queries, 0, 10),
                'pages' => array_keys($allPages),
                'authority_score' => $this->authorityScore($avgPos, $totalClicks, $totalImps, count($allPages), count($queries)),
                'avg_position' => round($avgPos, 1),
                'total_clicks' => $totalClicks,
                'total_impressions' => $totalImps,
            ];
        }

        usort($clusters, fn ($a, $b) => $b['authority_score'] <=> $a['authority_score']);
        $clusters = array_slice($clusters, 0, self::MAX_CLUSTERS);

        // Gaps: low-authority clusters with high impression volume = "you
        // get traffic on this topic but rank poorly — content opportunity".
        $gaps = [];
        foreach ($clusters as $c) {
            if ($c['authority_score'] < 40 && $c['total_impressions'] >= 200) {
                $gaps[] = [
                    'label' => $c['label'],
                    'suggested_action' => sprintf(
                        'Cluster averages position %.1f on %s impressions/90d — write a definitive page targeting "%s" plus 2–3 related queries from the cluster.',
                        $c['avg_position'],
                        number_format($c['total_impressions']),
                        $c['queries'][0] ?? $c['label'],
                    ),
                ];
            }
            if (count($gaps) >= 8) break;
        }

        $result = [
            'ok' => true,
            'clusters' => $clusters,
            'gaps' => $gaps,
        ];
        Cache::put($cacheKey, $result, Carbon::now()->addHours(self::CACHE_TTL_HOURS));
        return $result;
    }

    /**
     * 0..100 score blending position quality, traffic, and breadth.
     */
    private function authorityScore(float $avgPos, int $clicks, int $imps, int $pageCount, int $queryCount): int
    {
        // Position component (40%): 1 = 100, 100 = 0, log decay.
        $posComponent = $avgPos <= 1 ? 100 : ($avgPos >= 100 ? 0 : 100 - (log10($avgPos) / log10(100)) * 100);
        // Click traffic component (25%): saturates at 1000 clicks.
        $clickComponent = min(100, ($clicks / 10));
        // Impression breadth (20%): saturates at 5000 impressions.
        $impComponent = min(100, ($imps / 50));
        // Page coverage (10%): 1 page = 30, 5+ = 100.
        $pageComponent = $pageCount >= 5 ? 100 : 30 + ($pageCount - 1) * 17;
        // Query breadth (5%): 5+ queries = 100.
        $queryComponent = $queryCount >= 5 ? 100 : 50 + ($queryCount - 2) * 17;

        $score = ($posComponent * 0.40)
            + ($clickComponent * 0.25)
            + ($impComponent * 0.20)
            + ($pageComponent * 0.10)
            + ($queryComponent * 0.05);
        return max(0, min(100, (int) round($score)));
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $query): array
    {
        $parts = preg_split('/[^a-z0-9]+/u', strtolower($query)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) >= self::MIN_TOKEN_LENGTH && ! in_array($p, self::STOPWORDS, true)) {
                $out[$p] = true;
            }
        }
        return array_keys($out);
    }
}
