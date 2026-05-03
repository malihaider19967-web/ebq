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
        $row = \DB::table('rank_tracking_snapshots as s')
            ->join('rank_tracking_keywords as k', 's.rank_tracking_keyword_id', '=', 'k.id')
            ->where('k.website_id', $website->id)
            ->where('k.keyword_hash', \App\Models\RankTrackingKeyword::hashKeyword($focusKw))
            ->orderByDesc('s.checked_at')
            ->limit(1)
            ->select([
                's.position', 's.position_change', 's.serp_features',
                's.people_also_ask', 's.related_searches', 's.top_results',
                's.checked_at',
            ])
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'position' => $row->position !== null ? (int) $row->position : null,
            'position_change' => $row->position_change !== null ? (int) $row->position_change : null,
            'serp_features' => $this->jsonOrNull($row->serp_features) ?? [],
            'people_also_ask' => $this->jsonOrNull($row->people_also_ask) ?? [],
            'related_searches' => $this->jsonOrNull($row->related_searches) ?? [],
            'top_results' => $this->jsonOrNull($row->top_results) ?? [],
            'checked_at' => (string) $row->checked_at,
        ];
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
}
