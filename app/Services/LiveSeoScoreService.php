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
    public function score(Website $website, string $canonicalUrl, ?string $focusKeyword = null): array
    {
        $url = trim($canonicalUrl);
        if ($url === '' || ! $website->isAuditUrlForThisSite($url)) {
            return $this->unavailable('url_not_for_website');
        }

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz);
        $start30 = $end->copy()->subDays(29);

        // ── GSC totals for the URL (last 30 days)
        $gsc = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('page', $url)
            ->selectRaw('SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position, AVG(ctr) AS ctr')
            ->first();

        $impressions = (int) ($gsc->impressions ?? 0);
        $clicks = (int) ($gsc->clicks ?? 0);
        $avgPos = $gsc && $gsc->position !== null ? (float) $gsc->position : null;
        $avgCtr = $gsc && $gsc->ctr !== null ? (float) $gsc->ctr : null;

        if ($impressions === 0 && $avgPos === null) {
            return $this->unavailable('no_gsc_data_for_url');
        }

        // ── Focus-keyword rank (when supplied) — gets the heaviest weight.
        $kwRank = null;
        if ($focusKeyword !== null && $focusKeyword !== '') {
            $kwRow = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->whereDate('date', '>=', $start30->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->where('page', $url)
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
            ->where('page', $url)
            ->where('query', '!=', '')
            ->where('position', '<=', 100)
            ->distinct('query')
            ->count('query');

        // ── Cannibalization — is another URL competing for the same focus keyword?
        $cannibalized = false;
        if ($focusKeyword !== null && $focusKeyword !== '') {
            $competingPages = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->whereDate('date', '>=', $start30->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->whereRaw('LOWER(`query`) = ?', [mb_strtolower($focusKeyword)])
                ->where('page', '!=', $url)
                ->where('clicks', '>', 0)
                ->distinct('page')
                ->count('page');
            $cannibalized = $competingPages > 0;
        }

        // ── Audit / Lighthouse score (latest report).
        $auditScore = null;
        $latestAudit = PageAuditReport::query()
            ->where('website_id', $website->id)
            ->where('audit_url', $url)
            ->orderByDesc('created_at')
            ->first();
        if ($latestAudit && $latestAudit->lighthouse_performance !== null) {
            $auditScore = (int) round(((float) $latestAudit->lighthouse_performance) * 100);
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
        ];

        // Coverage (20%) — number of distinct ranking queries.
        $coverageScore = $this->coverageScore($coverage);
        $factors[] = [
            'key' => 'coverage',
            'label' => 'Topical coverage',
            'score' => $coverageScore,
            'weight' => 20,
            'detail' => sprintf('Ranks for %d distinct queries in the top 100', $coverage),
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
     * Position 1 = 100, position 100 = 0, decaying logarithmically so the
     * 1→3 jump is worth more than 30→50.
     */
    private function positionScore(float $pos): int
    {
        if ($pos <= 1) return 100;
        if ($pos >= 100) return 0;
        // Smooth log curve: positions 1..3 ≈ 95, 10 ≈ 70, 30 ≈ 40, 50 ≈ 25, 100 ≈ 0.
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

    private function unavailable(string $reason): array
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
        ];
    }
}
