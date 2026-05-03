<?php

namespace App\Services\Ai;

use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\ToolContext;
use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\AiContentBriefService;
use App\Services\BrandVoiceService;
use App\Services\EntityCoverageService;
use App\Services\NetworkInsightService;
use App\Services\TopicalGapService;
use Illuminate\Support\Carbon;

/**
 * Loads EBQ proprietary signals into a ToolContext, opt-in via the
 * tool's `meta()->contextSignals`.
 *
 * Lazy by design: a tool that doesn't ask for `gsc` does not run a
 * SearchConsoleData query. This keeps cheap utility tools (definition,
 * sentence-generator) cheap, while research tools get the full context
 * payload they need to win against RankMath.
 *
 * Every signal here is server-side only — the plugin never sees it.
 */
class ContextBuilder
{
    public function __construct(
        private readonly BrandVoiceService $brandVoice,
        private readonly AiContentBriefService $briefService,
        private readonly TopicalGapService $gapService,
        private readonly EntityCoverageService $entityService,
        private readonly NetworkInsightService $networkInsight,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input  raw user input — used to extract focus_keyword / url etc.
     */
    public function build(AiToolMeta $meta, Website $website, ?int $userId, array $input): ToolContext
    {
        $signals = $meta->contextSignals;
        $focusKw = $this->extractFocusKeyword($input);
        $url = $this->extractUrl($input);
        $country = strtolower(trim((string) ($input['country'] ?? ''))) ?: 'us';
        $language = strtolower(trim((string) ($input['language'] ?? ''))) ?: 'en';

        // Brand voice is loaded for ALL tools — it's the single biggest
        // differentiator and the cost is one indexed lookup.
        $voice = $this->brandVoice->forWebsite($website);

        return new ToolContext(
            website: $website,
            userId: $userId,
            brandVoice: $voice,
            gscTopQueries: in_array(AiTool::SIGNAL_GSC, $signals, true)
                ? $this->loadGscTopQueries($website, $url)
                : null,
            gscClustersForKeyword: in_array(AiTool::SIGNAL_GSC, $signals, true) && $focusKw !== ''
                ? $this->loadGscClustersForKeyword($website, $focusKw)
                : null,
            cachedBrief: in_array(AiTool::SIGNAL_BRIEF, $signals, true) && $focusKw !== ''
                ? $this->briefService->cachedBrief($website, $focusKw, $country)
                : null,
            topicalGaps: in_array(AiTool::SIGNAL_TOPICAL_GAPS, $signals, true) && $focusKw !== ''
                ? $this->loadTopicalGaps($website, $focusKw, $input, $country, $language)
                : null,
            entityCoverage: in_array(AiTool::SIGNAL_ENTITIES, $signals, true)
                ? $this->loadEntities($website, $url)
                : null,
            rankSnapshot: in_array(AiTool::SIGNAL_RANK_SNAPSHOT, $signals, true) && $focusKw !== ''
                ? $this->loadRankSnapshot($website, $focusKw)
                : null,
            internalLinkCandidates: in_array(AiTool::SIGNAL_INTERNAL_LINKS, $signals, true) && $focusKw !== ''
                ? $this->loadInternalLinkCandidates($website, $focusKw, $url)
                : null,
            networkInsight: in_array(AiTool::SIGNAL_NETWORK_INSIGHT, $signals, true) && $focusKw !== ''
                ? $this->networkInsight->forKeyword($focusKw, $country)
                : null,
            pageAudit: in_array(AiTool::SIGNAL_PAGE_AUDIT, $signals, true) && $url !== ''
                ? $this->loadPageAudit($website, $url)
                : null,
            seoAnalysis: in_array(AiTool::SIGNAL_SEO_ANALYSIS, $signals, true)
                ? $this->loadSeoAnalysis($website, $url, $input, $focusKw)
                : null,
            country: $country,
            language: $language,
        );
    }

    private function extractFocusKeyword(array $input): string
    {
        foreach (['focus_keyword', 'keyword', 'topic', 'seed_keyword', 'target_keyword'] as $k) {
            $v = $input[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        return '';
    }

    private function extractUrl(array $input): string
    {
        foreach (['url', 'page_url', 'post_url'] as $k) {
            $v = $input[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        $post = $input['post'] ?? null;
        if (is_array($post) && is_string($post['url'] ?? null)) {
            return trim($post['url']);
        }
        return '';
    }

    /**
     * @return list<array{query:string,clicks:int,impressions:int,position:float}>|null
     */
    private function loadGscTopQueries(Website $website, string $url): ?array
    {
        $q = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('query', '!=', '')
            ->whereBetween('date', [Carbon::now()->subDays(28), Carbon::now()]);

        if ($url !== '') {
            $q->where('page', $url);
        }

        $rows = $q
            ->selectRaw('query, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position')
            ->groupBy('query')
            ->orderByDesc('clicks')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->map(static fn ($r) => [
            'query' => (string) $r->query,
            'clicks' => (int) $r->clicks,
            'impressions' => (int) $r->impressions,
            'position' => round((float) $r->position, 1),
        ])->all();
    }

    /** @return array<string, mixed>|null */
    private function loadGscClustersForKeyword(Website $website, string $focusKw): ?array
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($focusKw))) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => mb_strlen($t) > 2));
        if ($tokens === []) {
            return null;
        }

        $q = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('query', '!=', '')
            ->whereBetween('date', [Carbon::now()->subDays(28), Carbon::now()]);

        $q->where(function ($w) use ($tokens) {
            foreach ($tokens as $t) {
                $w->orWhere('query', 'like', '%' . $t . '%');
            }
        });

        $rows = $q
            ->selectRaw('query, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS position')
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->limit(15)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'focus_keyword' => $focusKw,
            'related_queries' => $rows->map(static fn ($r) => [
                'query' => (string) $r->query,
                'clicks' => (int) $r->clicks,
                'impressions' => (int) $r->impressions,
                'position' => round((float) $r->position, 1),
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function loadTopicalGaps(Website $website, string $focusKw, array $input, string $country, string $language): ?array
    {
        $currentText = (string) ($input['current_html'] ?? $input['current_text'] ?? '');
        $currentText = trim(strip_tags($currentText));
        if (mb_strlen($currentText) < 200) {
            // The gap analyzer needs body text to compare.
            return null;
        }
        try {
            $res = $this->gapService->analyze($website, $focusKw, $currentText, $country, $language);
            return is_array($res) && ($res['available'] ?? false) === true ? $res : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function loadEntities(Website $website, string $url): ?array
    {
        if ($url === '') {
            return null;
        }
        try {
            $res = $this->entityService->analyze($website, $url);
            return is_array($res) ? $res : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function loadRankSnapshot(Website $website, string $focusKw): ?array
    {
        // Look up the most recent snapshot for this kw on this site.
        // Schema reference: rank_tracking_snapshots has no
        // `position_change` column — change is derived in
        // RankTrackingService at read time, not persisted.
        $row = \DB::table('rank_tracking_snapshots as s')
            ->join('rank_tracking_keywords as k', 's.rank_tracking_keyword_id', '=', 'k.id')
            ->where('k.website_id', $website->id)
            ->where('k.keyword_hash', \App\Models\RankTrackingKeyword::hashKeyword($focusKw))
            ->orderByDesc('s.checked_at')
            ->limit(1)
            ->select([
                's.position', 's.serp_features',
                's.people_also_ask', 's.related_searches', 's.top_results',
                's.checked_at',
            ])
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'position' => $row->position !== null ? (int) $row->position : null,
            'serp_features' => $this->jsonOrNull($row->serp_features) ?? [],
            'people_also_ask' => $this->jsonOrNull($row->people_also_ask) ?? [],
            'related_searches' => $this->jsonOrNull($row->related_searches) ?? [],
            'top_results' => $this->jsonOrNull($row->top_results) ?? [],
            'checked_at' => (string) $row->checked_at,
        ];
    }

    /**
     * Public access for non-AI callers (e.g. the editor's Research tab
     * composite endpoint) so we don't duplicate the GSC token-overlap
     * logic.
     *
     * @return list<array{url:string,anchor:string,topic:string,clicks:int}>|null
     */
    public function loadInternalLinkCandidatesPublic(Website $website, string $focusKw, string $url): ?array
    {
        return $this->loadInternalLinkCandidates($website, $focusKw, $url);
    }

    /** @return list<array{url:string,anchor:string,topic:string,clicks:int}>|null */
    private function loadInternalLinkCandidates(Website $website, string $focusKw, string $url): ?array
    {
        // Reuse AiWriterService::collectSmartLinks via a simplified
        // copy — the writer's logic is private, so we reproduce the
        // GSC token-overlap pull here.
        $tokens = preg_split('/\s+/', mb_strtolower(trim($focusKw))) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => mb_strlen($t) > 2));
        if ($tokens === []) {
            return null;
        }

        $q = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->where('query', '!=', '')
            ->whereBetween('date', [Carbon::now()->subDays(90), Carbon::now()]);
        if ($url !== '') {
            $q->where('page', '!=', $url);
        }
        $q->where(function ($w) use ($tokens) {
            foreach ($tokens as $t) {
                $w->orWhere('query', 'like', '%' . $t . '%');
            }
        });

        $rows = $q
            ->selectRaw('page, query, SUM(clicks) AS clicks')
            ->groupBy('page', 'query')
            ->orderByDesc('clicks')
            ->limit(40)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Pick the best query per page (max clicks).
        $byPage = [];
        foreach ($rows as $r) {
            $page = (string) $r->page;
            if ($page === '' || $page === $url) {
                continue;
            }
            if (! isset($byPage[$page]) || ($r->clicks > $byPage[$page]['clicks'])) {
                $byPage[$page] = [
                    'url' => $page,
                    'anchor' => (string) $r->query,
                    'topic' => (string) $r->query,
                    'clicks' => (int) $r->clicks,
                ];
            }
        }

        return array_slice(array_values($byPage), 0, 10);
    }

    /** @return array<string, mixed>|null */
    private function loadPageAudit(Website $website, string $url): ?array
    {
        try {
            $report = \App\Models\PageAuditReport::query()
                ->where('website_id', $website->id)
                ->where('page_url', $url)
                ->orderByDesc('created_at')
                ->first();
            if (! $report) {
                return null;
            }
            return [
                'http_status' => $report->http_status ?? null,
                'word_count' => $report->result['word_count'] ?? null,
                'h1' => $report->result['h1'] ?? null,
                'h2' => $report->result['h2'] ?? [],
                'schema_types' => $report->result['schema_types'] ?? [],
                'image_count' => $report->result['image_count'] ?? null,
                'link_count' => $report->result['link_count'] ?? null,
                'serp_benchmark' => $report->result['serp_benchmark'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function jsonOrNull(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Lightweight SEO state computed from the post's current HTML +
     * focus keyword + (optionally) cached entity coverage. Designed
     * to be injected into prompts so writing tools honour the gaps
     * the editor surfaces in real time.
     *
     * Costs:
     *   - HTML parsing: regex over current_html (cheap)
     *   - Entity coverage: cached lookup only (no LLM trigger), fully
     *     skipped when no audit exists yet
     *   - No GSC join, no LiveSeoScoreService call → safe to fire on
     *     every tool invocation without ballooning latency
     *
     * @return array<string, mixed>|null
     */
    private function loadSeoAnalysis(Website $website, string $url, array $input, string $focusKw): ?array
    {
        $html = (string) ($input['current_html'] ?? $input['current_text'] ?? '');
        if ($html === '' && $focusKw === '') {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
        $words = $text === '' ? 0 : str_word_count($text);

        // Focus-keyword presence and density.
        $kwCount = 0;
        if ($focusKw !== '' && $text !== '') {
            $kwCount = preg_match_all(
                '/\b' . preg_quote($focusKw, '/') . '\b/iu',
                $text,
            ) ?: 0;
        }
        $density = $words > 0 ? round(($kwCount / max(1, $words)) * 100, 2) : 0.0;

        // Structure flags.
        preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1m);
        $h1Text = isset($h1m[1]) ? trim(strip_tags($h1m[1])) : '';
        $hasH1 = $h1Text !== '';
        $kwInH1 = $hasH1 && $focusKw !== '' && stripos($h1Text, $focusKw) !== false;

        $h2Count = preg_match_all('/<h2\b[^>]*>/i', $html) ?: 0;
        $h3Count = preg_match_all('/<h3\b[^>]*>/i', $html) ?: 0;

        // "Flat sections" = the post is long but uses very few subheadings.
        $flatSections = $words >= 600 && $h2Count > 0 && $h3Count === 0;

        // Reading grade (Flesch-Kincaid approximation: 0.39 * (words/sentences)
        // + 11.8 * (syllables/words) - 15.59). Cheap heuristic — good enough
        // to know "this draft is too dense".
        $sentences = preg_match_all('/[.!?]+\s/', $text) ?: max(1, intdiv($words, 18));
        $syllables = $words > 0 ? $this->approximateSyllables($text) : 0;
        $grade = null;
        if ($words > 30) {
            $grade = max(0, round(
                0.39 * ($words / max(1, $sentences))
                + 11.8 * ($syllables / max(1, $words))
                - 15.59,
                1,
            ));
        }

        // Density target band — mainstream SEO heuristic, NOT a hard rule.
        $densityTargetBand = '0.6%–1.2%';
        $issues = [];
        if ($focusKw !== '' && $kwCount === 0) {
            $issues[] = ['severity' => 'high', 'title' => "Focus keyword '{$focusKw}' is missing from the post body."];
        } elseif ($focusKw !== '' && $density > 0 && $density < 0.4) {
            $issues[] = ['severity' => 'med', 'title' => "Focus keyword density is below 0.6% — add a few natural mentions."];
        } elseif ($density > 2.5) {
            $issues[] = ['severity' => 'med', 'title' => "Focus keyword density is above 2.5% — risk of keyword stuffing."];
        }
        if ($hasH1 && $focusKw !== '' && ! $kwInH1) {
            $issues[] = ['severity' => 'med', 'title' => 'The H1 does not contain the focus keyword.'];
        }
        if ($flatSections) {
            $issues[] = ['severity' => 'low', 'title' => 'Long sections lack H3 subheadings — section flow is flat.'];
        }
        if ($grade !== null && $grade > 11) {
            $issues[] = ['severity' => 'med', 'title' => "Reading grade {$grade}, target ≤10 — shorten sentences and use plainer words."];
        }

        // Cached entity coverage — never triggers LLM.
        $missingEntities = [];
        if ($url !== '') {
            try {
                $coverage = $this->entityService->preflight($website, $url);
                if (is_array($coverage) && ($coverage['ok'] ?? false) === true && is_array($coverage['missing'] ?? null)) {
                    foreach (array_slice($coverage['missing'], 0, 8) as $m) {
                        if (is_array($m) && is_string($m['entity'] ?? null)) {
                            $missingEntities[] = $m['entity'];
                        } elseif (is_string($m)) {
                            $missingEntities[] = $m;
                        }
                    }
                }
            } catch (\Throwable) {
                // best-effort — entity coverage is bonus context.
            }
        }

        // Bail if we have absolutely nothing worth surfacing.
        if ($issues === [] && $missingEntities === [] && $words === 0) {
            return null;
        }

        return [
            'focus_keyword' => $focusKw,
            'word_count' => $words,
            'kw_count' => $kwCount,
            'kw_density_pct' => $density,
            'kw_density_target' => $densityTargetBand,
            'reading_grade' => $grade,
            'reading_grade_target' => 10,
            'structure' => [
                'has_h1' => $hasH1,
                'kw_missing_h1' => $hasH1 && $focusKw !== '' && ! $kwInH1,
                'needs_h1' => ! $hasH1,
                'h2_count' => $h2Count,
                'h3_count' => $h3Count,
                'flat_sections' => $flatSections,
            ],
            'issues' => $issues,
            'missing_entities' => $missingEntities,
        ];
    }

    /**
     * Cheap syllable counter for the Flesch-Kincaid heuristic.
     * Matches contiguous vowel groups; not perfect but accurate to
     * within ~10% on typical English prose, which is plenty for the
     * "is this draft too dense?" decision the prompt cares about.
     */
    private function approximateSyllables(string $text): int
    {
        $words = preg_split('/\s+/', mb_strtolower($text)) ?: [];
        $total = 0;
        foreach ($words as $w) {
            $w = preg_replace('/[^a-z]/', '', $w) ?? '';
            if ($w === '') continue;
            $count = preg_match_all('/[aeiouy]+/i', $w) ?: 0;
            // Silent trailing 'e' adjustment.
            if (str_ends_with($w, 'e') && $count > 1) {
                $count--;
            }
            $total += max(1, $count);
        }
        return $total;
    }
}
