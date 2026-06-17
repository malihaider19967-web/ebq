<?php

namespace App\Services;

use App\Jobs\RunCustomPageAudit;
use App\Models\CustomPageAudit;
use App\Models\PageAuditReport;
use App\Models\Website;
use Illuminate\Support\Carbon;

/**
 * Orchestrates the dashboard striking-distance "Fix this keyword" playbook.
 *
 * Given a (websiteId, keyword, pageUrl) opportunity it stitches together the
 * four fix levers the playbook surfaces — all from infrastructure that already
 * exists:
 *
 *   1. On-page recommendations + on-page metrics — from a keyword-aware
 *      {@see PageAuditService} run (recommendations come from RecommendationEngine).
 *   2. AI title + meta rewrites — {@see AiSnippetRewriterService}, fed from the
 *      audit's fetched title/meta/body-excerpt + SERP competitor titles.
 *   3. Content brief / topical gaps — {@see AiContentBriefService}.
 *   4. Internal-link suggestions — {@see AiContentBriefService::internalLinkTargets()}.
 *
 * The AI services were built for the WordPress editor and take an int $postId
 * purely as a cache key; they never load a Post. Striking-distance keywords are
 * GSC page URLs with no WP Post, so we pass a deterministic synthetic id derived
 * from (website, url) — see {@see syntheticPostId()}.
 */
class StrikingDistanceFixService
{
    public function __construct(
        private readonly AiSnippetRewriterService $rewriter,
        private readonly AiContentBriefService $briefs,
    ) {}

    /**
     * Deterministic, positive 31-bit synthetic id for cache keying. Derived
     * from (website, url) so repeat clicks hit the same 7-day cache slot, and
     * masked to 31 bits so it can never collide with a real WP post id used by
     * the plugin's snippet cache. Same URL → same id.
     */
    public function syntheticPostId(string $websiteId, string $pageUrl): int
    {
        $hex = substr(hash('xxh3', $websiteId.'|'.$pageUrl), 0, 8);

        return (int) (hexdec($hex) & 0x7FFFFFFF);
    }

    /**
     * A recent completed audit we can reuse instead of paying for a new one.
     * Returns null when nothing fresh (< $maxAgeHours) exists.
     */
    public function findFreshReport(string $websiteId, string $pageUrl, int $maxAgeHours = 24): ?PageAuditReport
    {
        return PageAuditReport::query()
            ->where('website_id', $websiteId)
            ->where('page_hash', hash('sha256', $pageUrl))
            ->where('status', 'completed')
            ->where('audited_at', '>=', Carbon::now()->subHours($maxAgeHours))
            ->latest('audited_at')
            ->first();
    }

    /**
     * Queue a full keyword-aware audit (reuses the CustomPageAudit pipeline).
     * Deduped: if one is already queued/running for this (website, url, user)
     * we attach to it rather than paying twice. Returns the audit row to poll.
     */
    public function queueAudit(string $websiteId, string $userId, string $pageUrl, string $keyword, ?string $gl): CustomPageAudit
    {
        $active = CustomPageAudit::findActiveFor($websiteId, $pageUrl, $userId);
        if ($active instanceof CustomPageAudit) {
            return $active;
        }

        $audit = CustomPageAudit::queue(
            websiteId: $websiteId,
            userId: $userId,
            pageUrl: $pageUrl,
            targetKeyword: $keyword,
            serpSampleGl: $gl,
            source: CustomPageAudit::SOURCE_KEYWORD_FIX,
        );

        RunCustomPageAudit::dispatch($audit->id);

        return $audit;
    }

    /**
     * On-page recommendations from the audit, as produced by RecommendationEngine.
     *
     * @return list<array{id: string, section: string, severity: string, title: string, why: string, fix: string}>
     */
    public function recommendations(PageAuditReport $report): array
    {
        $recs = $report->result['recommendations'] ?? [];

        return is_array($recs) ? array_values(array_filter($recs, 'is_array')) : [];
    }

    /**
     * A compact keyword-targeted snapshot for the on-page summary row:
     * keyword presence in title/H1/meta and word count vs the SERP top-3 median.
     *
     * @return array{
     *   keyword: string,
     *   in_title: bool,
     *   in_meta: bool,
     *   in_h1: bool,
     *   word_count: int,
     *   competitor_word_count_median: ?int,
     *   word_count_gap: ?int,
     * }
     */
    public function onPageMetrics(PageAuditReport $report, string $keyword): array
    {
        $result = is_array($report->result) ? $report->result : [];
        $meta = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        $content = is_array($result['content'] ?? null) ? $result['content'] : [];

        $needle = mb_strtolower(trim($keyword));
        $contains = static function (string $haystack) use ($needle): bool {
            return $needle !== '' && str_contains(mb_strtolower($haystack), $needle);
        };

        $h1Text = '';
        foreach (($content['headings'] ?? []) as $h) {
            if (is_array($h) && (int) ($h['level'] ?? 0) === 1) {
                $h1Text .= ' '.(string) ($h['text'] ?? '');
            }
        }

        $wordCount = (int) ($content['word_count'] ?? 0);
        $competitorMedian = $this->competitorWordCountMedian($result);

        return [
            'keyword' => $keyword,
            'in_title' => $contains((string) ($meta['title'] ?? '')),
            'in_meta' => $contains((string) ($meta['meta_description'] ?? '')),
            'in_h1' => $contains($h1Text),
            'word_count' => $wordCount,
            'competitor_word_count_median' => $competitorMedian,
            'word_count_gap' => $competitorMedian !== null ? $competitorMedian - $wordCount : null,
        ];
    }

    /**
     * AI title + meta rewrites for the keyword, built from the audit's already
     * fetched copy (title/meta/body excerpt) + the SERP competitor titles — so
     * no WordPress Post is required.
     *
     * @return array{ok: bool, intent?: string, rewrites?: list<array<string, mixed>>, model?: string, cached?: bool, error?: string}
     */
    public function snippetRewrites(string $websiteId, string $pageUrl, string $keyword, PageAuditReport $report, string $intent): array
    {
        $result = is_array($report->result) ? $report->result : [];
        $meta = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        $content = is_array($result['content'] ?? null) ? $result['content'] : [];

        $input = [
            'focus_keyword' => $keyword,
            'current_title' => (string) ($meta['title'] ?? ''),
            'current_meta' => (string) ($meta['meta_description'] ?? ''),
            'content_excerpt' => (string) ($content['body_excerpt'] ?? ''),
            'competitor_titles' => $this->competitorTitles($result),
            'intent' => $intent,
        ];

        return $this->rewriter->rewrite($this->syntheticPostId($websiteId, $pageUrl), $input);
    }

    /**
     * Content brief for the keyword. Returns a cached brief for free when one
     * exists; only call this when the user opts to generate (it spends a Serper
     * credit). The unused $postId arg satisfies the service signature.
     *
     * @return array{ok: bool, brief?: array<string, mixed>, cached?: bool, error?: string}
     */
    public function brief(Website $website, string $keyword, ?string $country, bool $generate = false): array
    {
        $gl = $country !== null && $country !== '' ? $country : 'us';

        $cached = $this->briefs->cachedBrief($website, $keyword, $gl);
        if (is_array($cached)) {
            return ['ok' => true, 'brief' => $cached['brief'] ?? $cached, 'cached' => true];
        }

        if (! $generate) {
            return ['ok' => false, 'error' => 'not_generated'];
        }

        return $this->briefs->brief($website, $this->syntheticPostId($website->id, $keyword), [
            'focus_keyword' => $keyword,
            'country' => $gl,
        ]);
    }

    /**
     * Internal-link suggestions — existing high-traffic pages that should link
     * to the ranking URL with the keyword as anchor. Works without any AI call.
     * The ranking page itself is excluded so we never suggest a self-link.
     *
     * @return list<array{url: string, anchor_hint: string, clicks_30d: int}>
     */
    public function internalLinks(Website $website, string $keyword, ?string $excludePageUrl = null): array
    {
        return $this->briefs->internalLinkTargets($website, $keyword, $excludePageUrl);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private function competitorTitles(array $result): array
    {
        $competitors = $result['benchmark']['competitors'] ?? [];
        if (! is_array($competitors)) {
            return [];
        }

        $titles = [];
        foreach ($competitors as $c) {
            $title = is_array($c) ? trim((string) ($c['title'] ?? '')) : '';
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return array_slice($titles, 0, 3);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function competitorWordCountMedian(array $result): ?int
    {
        $competitors = $result['benchmark']['competitors'] ?? [];
        if (! is_array($competitors)) {
            return null;
        }

        $counts = [];
        foreach ($competitors as $c) {
            $wc = is_array($c) ? ($c['word_count'] ?? null) : null;
            if (is_numeric($wc) && (int) $wc > 0) {
                $counts[] = (int) $wc;
            }
        }

        if ($counts === []) {
            return null;
        }

        sort($counts);
        $mid = intdiv(count($counts), 2);

        return count($counts) % 2 === 0
            ? (int) round(($counts[$mid - 1] + $counts[$mid]) / 2)
            : $counts[$mid];
    }
}
