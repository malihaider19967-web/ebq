<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\RankTrackingKeyword;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Support\Carbon;

/**
 * "Live" SEO score — what Google actually thinks of the post, derived from
 * GSC rank + CTR + Lighthouse audit + cannibalization presence + coverage
 * breadth. Distinct from the editor's local self-check (which only knows
 * about word counts and keyword density).
 *
 * The killer signal is the *delta* between the two scores — a high
 * self-check + low live score means "your post follows every rule but
 * Google still doesn't rank it; here's why" — that diff is what pulls
 * users back to the EBQ platform when an answer is needed.
 *
 * URL matching uses `PluginInsightResolver::__publicPageVariants()` so we
 * match GSC rows regardless of:
 *   - trailing slash drift  (`/abc/xyz` vs `/abc/xyz/`)
 *   - www vs apex            (`www.x.com` vs `x.com`)
 *   - scheme                 (`http://` vs `https://`)
 *   - case                   (lowercased URLs in storage)
 * The same matcher handles 12 cross-variants in one indexed `whereIn` lookup.
 *
 * Pure data composition, no LLM call. Returns:
 *   [
 *     'score' => 0..100,
 *     'label' => 'Bad' | 'Needs work' | 'Good',
 *     'available' => bool,                  // false when no data yet
 *     'factors' => list<{key,label,score,weight,detail}>,
 *     'explanation' => string,              // one-line summary
 *   ]
 */
class LiveSeoScoreService
{
    public function __construct(
        private readonly PluginInsightResolver $resolver,
    ) {}

    public function score(Website $website, string $canonicalUrl, ?string $focusKeyword = null): array
    {
        $url = trim($canonicalUrl);
        if ($url === '' || ! $website->isAuditUrlForThisSite($url)) {
            return $this->unavailable('url_not_for_website', ['url' => $url, 'domain' => $website->domain]);
        }

        // Strict 12-way variant set covering trailing slash / www / scheme
        // / case. Used standalone for cannibalization (whereNotIn) and the
        // diagnostic counts. The actual GSC reads use applyPageMatch which
        // adds a LIKE fallback on top of variants for query strings / AMP /
        // CDN drift the variant generator can't enumerate exactly.
        $variants = $this->resolver->__publicPageVariants($url);

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz);
        $start30 = $end->copy()->subDays(29);

        // ── GSC totals for the URL (last 30 days). Match via the resolver's
        // strict + LIKE matcher so we don't miss query-string / AMP variants.
        $gsc = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->tap(fn ($q) => $this->resolver->__publicApplyPageMatch($q, $url))
            ->selectRaw('SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position, AVG(ctr) AS ctr')
            ->first();

        $impressions = (int) ($gsc->impressions ?? 0);
        $clicks = (int) ($gsc->clicks ?? 0);
        $avgPos = $gsc && $gsc->position !== null ? (float) $gsc->position : null;
        $avgCtr = $gsc && $gsc->ctr !== null ? (float) $gsc->ctr : null;

        if ($impressions === 0 && $avgPos === null) {
            return $this->unavailable('no_gsc_data_for_url', $this->buildDiagnostics($website, $url, $variants));
        }

        // ── Focus-keyword rank (when supplied) — gets the heaviest weight.
        $kwRank = null;
        if ($focusKeyword !== null && $focusKeyword !== '') {
            $kwRow = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->whereDate('date', '>=', $start30->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->tap(fn ($q) => $this->resolver->__publicApplyPageMatch($q, $url))
                ->whereRaw('LOWER(`query`) = ?', [mb_strtolower($focusKeyword)])
                ->selectRaw('AVG(position) AS position, SUM(impressions) AS impressions')
                ->first();
            if ($kwRow && $kwRow->position !== null && (int) $kwRow->impressions > 0) {
                $kwRank = (float) $kwRow->position;
            }
        }

        // ── Coverage breadth — how many distinct queries the URL ranks for in top 100.
        $coverage = (int) SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->tap(fn ($q) => $this->resolver->__publicApplyPageMatch($q, $url))
            ->where('query', '!=', '')
            ->where('position', '<=', 100)
            ->distinct('query')
            ->count('query');

        // ── Cannibalization — is another URL competing for the same focus keyword?
        // Strict variants only here (no LIKE) so a query-string twin of OUR URL
        // doesn't get counted as a competing page.
        $cannibalized = false;
        if ($focusKeyword !== null && $focusKeyword !== '') {
            $competingPages = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->whereDate('date', '>=', $start30->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->whereRaw('LOWER(`query`) = ?', [mb_strtolower($focusKeyword)])
                ->whereNotIn('page', $variants)
                ->where('clicks', '>', 0)
                ->distinct('page')
                ->count('page');
            $cannibalized = $competingPages > 0;
        }

        // ── Audit / Lighthouse score (latest report) — match by variant too.
        // PageAuditReport stores the URL in `page` and we look it up by
        // sha256(`page_hash`) for index-friendly matching, mirroring
        // PluginInsightResolver. The Lighthouse performance score is nested
        // inside the JSON `result` blob (already on a 0–100 scale).
        $auditScore = null;
        $variantHashes = array_map(static fn (string $v) => hash('sha256', $v), $variants);
        $latestAudit = PageAuditReport::query()
            ->where('website_id', $website->id)
            ->whereIn('page_hash', $variantHashes)
            ->latest('audited_at')
            ->first();
        if ($latestAudit && is_array($latestAudit->result)) {
            $cwv = $latestAudit->result['core_web_vitals'] ?? [];
            $mobile = is_array($cwv['mobile'] ?? null) ? $cwv['mobile'] : [];
            $desktop = is_array($cwv['desktop'] ?? null) ? $cwv['desktop'] : [];
            $perf = $mobile['performance_score'] ?? $desktop['performance_score'] ?? null;
            if ($perf !== null) {
                $auditScore = (int) round((float) $perf);
            }
        }

        // ── Tracked-keyword bonus — explicit tracker entry adds confidence.
        $tracked = false;
        if ($focusKeyword !== null && $focusKeyword !== '') {
            $tracked = RankTrackingKeyword::query()
                ->where('website_id', $website->id)
                ->where('keyword_hash', RankTrackingKeyword::hashKeyword($focusKeyword))
                ->where('is_active', true)
                ->exists();
        }

        // ── Build factor breakdown ────────────────────────────────
        $factors = [];

        // Rank (35%) — focus-keyword rank if known, else average page rank.
        $rankBasis = $kwRank ?? $avgPos ?? 100.0;
        $rankScore = $this->positionScore($rankBasis);
        $factors[] = [
            'key' => 'rank',
            'label' => $kwRank !== null ? 'Focus-keyword rank' : 'Average page rank',
            'score' => $rankScore,
            'weight' => 35,
            'detail' => $kwRank !== null
                ? sprintf('Position %.1f for "%s"', $kwRank, $focusKeyword)
                : sprintf('Avg position %.1f across all queries', $rankBasis),
            'recommendation' => $rankScore < 65
                ? ($kwRank !== null
                    ? sprintf(
                        'Add internal links to this page using "%s" as anchor text and place the keyphrase in an H2/H3. Strengthen the topical depth (covered subtopics, supporting media) to climb out of position %.0f.',
                        $focusKeyword !== null && $focusKeyword !== '' ? $focusKeyword : 'your focus keyphrase',
                        $rankBasis
                    )
                    : 'Set a focus keyphrase so we can score the page on a specific query, then strengthen internal links and on-page placement for it.')
                : null,
        ];

        // CTR (15%) — actual CTR vs expected for the rank.
        $expectedCtr = $this->expectedCtrForPosition($rankBasis);
        $ctrScore = $this->ctrScore($avgCtr ?? 0, $expectedCtr);
        $factors[] = [
            'key' => 'ctr',
            'label' => 'Click-through rate',
            'score' => $ctrScore,
            'weight' => 15,
            'detail' => sprintf(
                'CTR %.2f%% (expected %.2f%% for rank %.0f)',
                ($avgCtr ?? 0) * 100,
                $expectedCtr * 100,
                $rankBasis
            ),
            'recommendation' => $ctrScore < 65
                ? 'Rewrite the SEO title and meta description so the snippet earns more clicks: lead with the keyphrase, add a year or specific number, and answer the searcher\'s intent in plain language.'
                : null,
        ];

        // Coverage (20%) — number of distinct ranking queries.
        $coverageScore = $this->coverageScore($coverage);
        $factors[] = [
            'key' => 'coverage',
            'label' => 'Topical coverage',
            'score' => $coverageScore,
            'weight' => 20,
            'detail' => sprintf('Ranks for %d distinct queries in the top 100', $coverage),
            'recommendation' => $coverageScore < 65
                ? 'Add 1–2 sections covering related sub-questions and long-tail variants of the focus keyphrase. Use the "Topical gaps vs. top SERP" panel above for specific subtopic ideas competitors cover.'
                : null,
        ];

        // Cannibalization (15%) — penalty when present.
        $cannibalScore = $cannibalized ? 30 : 100;
        $factors[] = [
            'key' => 'cannibalization',
            'label' => 'No cannibalization',
            'score' => $cannibalScore,
            'weight' => 15,
            'detail' => $cannibalized
                ? 'Another URL on this site is also ranking for the focus keyword.'
                : 'No competing pages on the site for this query.',
            'recommendation' => $cannibalized
                ? 'Find the other ranking URL in the HQ → Insights → Cannibalization view and either 301-redirect it into this page or shift its targeting to a different keyphrase.'
                : null,
        ];

        // Audit (10%) — Lighthouse performance.
        $auditFactor = $auditScore ?? 60; // neutral default when no audit
        $factors[] = [
            'key' => 'audit',
            'label' => 'Page performance',
            'score' => $auditFactor,
            'weight' => 10,
            'detail' => $auditScore !== null
                ? sprintf('Lighthouse: %d/100', $auditScore)
                : 'No recent Lighthouse audit on file.',
            'recommendation' => $auditScore !== null && $auditScore < 65
                ? 'Compress and lazy-load images, defer third-party JS (analytics, chat widgets), and inline critical CSS. Re-run the audit from HQ → Page Audits to verify.'
                : ($auditScore === null
                    ? 'Run a page audit from HQ → Page Audits so the live score can factor in real Lighthouse performance.'
                    : null),
        ];

        // Tracked-keyword (5%) — small nudge.
        $factors[] = [
            'key' => 'tracked',
            'label' => 'Tracked in Rank Tracker',
            'score' => $tracked ? 100 : 50,
            'weight' => 5,
            'detail' => $tracked
                ? 'Focus keyword is in your Rank Tracker.'
                : 'Focus keyword is not yet tracked. Tracking sharpens the score.',
            'recommendation' => $tracked
                ? null
                : 'Add this keyphrase to your Rank Tracker so EBQ can monitor weekly position, SERP features, and competitor changes.',
            'action' => $tracked || $focusKeyword === null || $focusKeyword === ''
                ? null
                : [
                    'kind' => 'track-keyword',
                    'label' => 'Add to Rank Tracker',
                    'keyword' => $focusKeyword,
                ],
        ];

        // ── Weighted composite ───────────────────────────────────
        $weightSum = array_sum(array_column($factors, 'weight'));
        $weighted = 0;
        foreach ($factors as $f) {
            $weighted += $f['score'] * ($f['weight'] / $weightSum);
        }
        $score = max(0, min(100, (int) round($weighted)));

        $label = $score >= 65 ? 'Good' : ($score >= 45 ? 'Needs work' : 'Bad');
        $explanation = $this->buildExplanation($score, $rankBasis, $cannibalized, $coverage, $kwRank !== null);

        return [
            'score' => $score,
            'label' => $label,
            'available' => true,
            'factors' => $factors,
            'explanation' => $explanation,
        ];
    }

    /**
     * When GSC returns nothing for the URL, attach diagnostic counters
     * to the unavailable response so we can quickly see WHY: did the
     * site sync at all? Are there any rows for this host? Are there
     * similar URLs (path LIKE) that suggest a normalization mismatch?
     * Same shape the focus-keyword-suggestions endpoint already uses.
     */
    private function buildDiagnostics(Website $website, string $url, array $variants): array
    {
        $totalRows = (int) SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->count();
        $latestSync = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->max('date');

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $hostNoWww = preg_replace('/^www\./', '', $host) ?: $host;

        $similar = [];
        if ($path !== '/' && $path !== '') {
            $tail = '%' . addcslashes(rtrim($path, '/'), '\\%_') . '%';
            $similar = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->where('page', 'LIKE', $tail)
                ->select('page')->distinct()->limit(5)
                ->pluck('page')->all();
        } elseif ($hostNoWww !== '') {
            $h = addcslashes($hostNoWww, '\\%_');
            $similar = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->where(function ($q) use ($h) {
                    $q->where('page', 'LIKE', '%://' . $h)
                      ->orWhere('page', 'LIKE', '%://' . $h . '/')
                      ->orWhere('page', 'LIKE', '%://www.' . $h)
                      ->orWhere('page', 'LIKE', '%://www.' . $h . '/')
                      ->orWhere('page', 'LIKE', '%://' . $h . '?%')
                      ->orWhere('page', 'LIKE', '%://' . $h . '/?%')
                      ->orWhere('page', 'LIKE', '%://www.' . $h . '?%')
                      ->orWhere('page', 'LIKE', '%://www.' . $h . '/?%');
                })
                ->select('page')->distinct()->limit(5)
                ->pluck('page')->all();
        }

        return [
            'queried_url'            => $url,
            'queried_path'           => $path,
            'tried_variants'         => $variants,
            'gsc_rows_total_all_time'=> $totalRows,
            'gsc_last_sync_date'     => $latestSync ? (string) $latestSync : null,
            'similar_urls_in_gsc'    => $similar,
        ];
    }

    /**
     * Position 1 = 100, position 100 = 0, decaying logarithmically so the
     * 1→3 jump is worth more than 30→50.
     */
    private function positionScore(float $pos): int
    {
        if ($pos <= 1) return 100;
        if ($pos >= 100) return 0;
        $score = 100 - (log10($pos) / log10(100)) * 100;
        return (int) round(max(0, min(100, $score)));
    }

    /**
     * Approximate AWR / Sistrix CTR-by-rank curve. Position 1 ≈ 30%,
     * 5 ≈ 6%, 10 ≈ 2.5%, 20+ ≈ <1%. Used as the "expected" baseline.
     */
    private function expectedCtrForPosition(float $pos): float
    {
        if ($pos <= 1) return 0.30;
        if ($pos <= 2) return 0.20;
        if ($pos <= 3) return 0.13;
        if ($pos <= 4) return 0.10;
        if ($pos <= 5) return 0.07;
        if ($pos <= 10) return 0.025;
        if ($pos <= 20) return 0.010;
        return 0.005;
    }

    /**
     * 100 = at expected, 130%+ = 100 (capped), under expected scales linearly.
     */
    private function ctrScore(float $actual, float $expected): int
    {
        if ($expected <= 0) return 50;
        $ratio = $actual / $expected;
        if ($ratio >= 1.0) return 100;
        return (int) round(max(0, min(100, $ratio * 100)));
    }

    /**
     * 0 queries = 0, 5 ≈ 50, 20+ ≈ 100. Plateau so a 200-query post
     * doesn't dwarf a focused 30-query post.
     */
    private function coverageScore(int $count): int
    {
        if ($count <= 0) return 0;
        if ($count >= 20) return 100;
        return (int) round(($count / 20) * 100);
    }

    private function buildExplanation(int $score, float $rankBasis, bool $cannibalized, int $coverage, bool $kwKnown): string
    {
        if ($score >= 65) {
            return sprintf(
                'Live performance is strong. Average rank %.0f across %d queries. Keep adding internal links + tracking keywords to defend the position.',
                $rankBasis, $coverage
            );
        }
        $reasons = [];
        if ($rankBasis > 20) $reasons[] = 'rank is below page 2';
        if ($cannibalized)   $reasons[] = 'another URL competes for the same keyword';
        if ($coverage < 5)   $reasons[] = 'the page only ranks for a handful of queries';
        if (! $kwKnown)      $reasons[] = "we don't have a confirmed focus-keyword rank yet";
        if (empty($reasons)) $reasons[] = 'multiple soft signals are below benchmark';
        return 'Why low: ' . implode(', ', $reasons) . '.';
    }

    private function unavailable(string $reason, array $debug = []): array
    {
        return [
            'score' => 0,
            'label' => 'Unavailable',
            'available' => false,
            'factors' => [],
            'explanation' => match ($reason) {
                'url_not_for_website' => 'This URL doesn\'t belong to your connected website.',
                'no_gsc_data_for_url' => 'No Google Search Console data for this URL yet — give Google a few days to crawl + impressions to accrue.',
                default => 'Live score is not available for this post.',
            },
            'reason' => $reason,
            'debug'  => $debug,
        ];
    }
}
