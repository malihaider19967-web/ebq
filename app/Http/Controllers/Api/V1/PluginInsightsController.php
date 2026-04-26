<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\MatchRedirectFor404Job;
use App\Models\RedirectSuggestion;
use App\Models\Website;
use App\Services\AiContentBriefService;
use App\Services\AiSnippetRewriterService;
use App\Services\LiveSeoScoreService;
use App\Services\PluginInsightResolver;
use App\Services\ReportDataService;
use App\Services\TopicalGapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class PluginInsightsController extends Controller
{
    public function __construct(
        private readonly PluginInsightResolver $resolver,
        private readonly ReportDataService $reports,
    ) {
    }

    /**
     * Single-post payload for the Gutenberg sidebar.
     *   GET /api/v1/posts/{externalPostId}/insights?url=...
     */
    public function showPost(Request $request, string $externalPostId): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $url = (string) $request->query('url', '');
        $keyword = (string) $request->query('target_keyword', '');

        $payload = $this->resolver->forUrl($website, $url, $keyword ?: null, $externalPostId);

        return response()->json($payload);
    }

    /**
     * Bulk variant for the WP admin post list.
     *   GET /api/v1/posts?urls[]=...&urls[]=...
     */
    public function indexPosts(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $urls = (array) $request->query('urls', []);
        $urls = array_slice(array_filter($urls, 'is_string'), 0, 100);

        return response()->json([
            'results' => $this->resolver->bulkForUrls($website, $urls),
        ]);
    }

    /**
     * Dashboard-widget counts.
     *   GET /api/v1/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $counts = $this->reports->insightCounts($website->id);

        return response()->json([
            'website_id' => $website->id,
            'domain' => $website->domain,
            'counts' => $counts,
            'alert' => [
                'last_traffic_drop_alert_at' => $website->last_traffic_drop_alert_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Ranked focus-keyword candidates for the given post URL.
     *   GET /api/v1/posts/{externalPostId}/focus-keyword-suggestions?url=...
     */
    public function focusKeywordSuggestions(Request $request, string $externalPostId): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $url = (string) $request->query('url', '');

        // Bumped manually each time we change focus-keyword-suggestions code,
        // so the editor can show "is the new code live?" in the diagnostic.
        $codeVersion = 'fks-2026-04-25-c';

        $diagnostic = null;
        $suggestions = [];
        $debug = ['code_version' => $codeVersion];

        if ($url === '') {
            $diagnostic = 'missing_url';
        } elseif (! $website->isAuditUrlForThisSite($url)) {
            $diagnostic = 'url_not_for_website';
        } else {
            $suggestions = $this->resolver->focusKeywordSuggestions($website, $url);
            if (empty($suggestions)) {
                $diagnostic = 'no_gsc_data';

                // Probe the same shape the resolver uses, so we can see in
                // the editor whether: the variants are right, the whereIn
                // ought to match, and the LIKE fallback is doing anything.
                $variants = $this->resolver->__publicPageVariants($url);
                $strictMatchCount = \App\Models\SearchConsoleData::query()
                    ->where('website_id', $website->id)
                    ->whereIn('page', $variants)
                    ->where('query', '!=', '')
                    ->count();
                $strictDistinctPages = \App\Models\SearchConsoleData::query()
                    ->where('website_id', $website->id)
                    ->whereIn('page', $variants)
                    ->select('page')->distinct()->limit(5)
                    ->pluck('page')->all();

                $debug['tried_variants'] = $variants;
                $debug['strict_match_rows'] = (int) $strictMatchCount;
                $debug['strict_match_pages'] = $strictDistinctPages;

                // When empty, give the editor enough context to explain WHY:
                // is GSC syncing at all? does this URL exist in GSC under a
                // different shape? Cheap to compute, very valuable to debug.
                $totalRows = \App\Models\SearchConsoleData::query()
                    ->where('website_id', $website->id)
                    ->count();
                $latestSync = \App\Models\SearchConsoleData::query()
                    ->where('website_id', $website->id)
                    ->max('date');

                // Show up to 5 GSC URLs the user can compare against.
                // For non-root paths: anything containing the path.
                // For root: any URL that looks like a homepage (host with
                // optional / and optional ?query). Surfacing what GSC
                // actually has is what makes the empty state debuggable.
                $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
                $hostNoWww = preg_replace('/^www\./', '', $host) ?: $host;

                $similar = [];
                if ($path !== '/' && $path !== '') {
                    $tail = '%'.addcslashes(rtrim($path, '/'), '\\%_').'%';
                    $similar = \App\Models\SearchConsoleData::query()
                        ->where('website_id', $website->id)
                        ->where('page', 'LIKE', $tail)
                        ->select('page')->distinct()->limit(5)
                        ->pluck('page')->all();
                } elseif ($hostNoWww !== '') {
                    $similar = \App\Models\SearchConsoleData::query()
                        ->where('website_id', $website->id)
                        ->where(function ($q) use ($hostNoWww) {
                            $h = addcslashes($hostNoWww, '\\%_');
                            $q->where('page', 'LIKE', '%://'.$h)
                              ->orWhere('page', 'LIKE', '%://'.$h.'/')
                              ->orWhere('page', 'LIKE', '%://www.'.$h)
                              ->orWhere('page', 'LIKE', '%://www.'.$h.'/')
                              ->orWhere('page', 'LIKE', '%://'.$h.'?%')
                              ->orWhere('page', 'LIKE', '%://'.$h.'/?%')
                              ->orWhere('page', 'LIKE', '%://www.'.$h.'?%')
                              ->orWhere('page', 'LIKE', '%://www.'.$h.'/?%');
                        })
                        ->select('page')->distinct()->limit(5)
                        ->pluck('page')->all();
                }

                $debug = array_merge($debug, [
                    'gsc_rows_total_all_time' => (int) $totalRows,
                    'gsc_last_sync_date'      => $latestSync ? (string) $latestSync : null,
                    'queried_url'             => $url,
                    'queried_path'            => $path,
                    'similar_urls_in_gsc'     => $similar,
                ]);
            }
        }

        return response()->json([
            'external_post_id' => $externalPostId,
            'url' => $url,
            'website_domain' => $website->domain,
            'suggestions' => $suggestions,
            'diagnostic' => $diagnostic,
            'debug' => $debug,
        ]);
    }

    /**
     * Related keyphrase suggestions for a focus keyword — Yoast-Premium
     * "related keyphrases" backed by GSC + rank-tracker SERP captures
     * (no Semrush, no per-keystroke external API spend).
     *   GET /api/v1/posts/{externalPostId}/related-keywords?keyword=...&url=...
     */
    public function relatedKeywords(Request $request, string $externalPostId): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $keyword = (string) $request->query('keyword', '');
        $url = (string) $request->query('url', '');

        $diagnostic = null;
        $suggestions = [];

        if (trim($keyword) === '') {
            $diagnostic = 'missing_keyword';
        } else {
            $suggestions = $this->resolver->relatedKeywords(
                $website,
                $keyword,
                $url !== '' ? $url : null,
            );
            if (empty($suggestions)) {
                $diagnostic = 'no_related_data';
            }
        }

        return response()->json([
            'external_post_id' => $externalPostId,
            'keyword' => $keyword,
            'suggestions' => $suggestions,
            'diagnostic' => $diagnostic,
        ]);
    }

    /**
     * Internal-link suggestions: other URLs on this website worth linking
     * *to* from the post being edited. Ranked by Search Console performance,
     * not just word similarity.
     *   GET /api/v1/posts/{externalPostId}/internal-link-suggestions?url=...&keyword=...&title=...
     */
    public function internalLinkSuggestions(Request $request, string $externalPostId): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $url = (string) $request->query('url', '');
        $keyword = (string) $request->query('keyword', '');
        $title = (string) $request->query('title', '');

        $suggestions = $this->resolver->internalLinkSuggestions(
            $website,
            $url,
            $keyword !== '' ? $keyword : null,
            $title !== '' ? $title : null,
        );

        return response()->json([
            'external_post_id' => $externalPostId,
            'url' => $url,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Live competitor SERP (top 5) for a chosen query on this site's market.
     *   GET /api/v1/posts/{externalPostId}/serp-preview?query=...
     */
    public function serpPreview(Request $request, string $externalPostId): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $query = (string) $request->query('query', '');

        return response()->json([
            'external_post_id' => $externalPostId,
        ] + $this->resolver->serpPreview($website, $query));
    }

    /**
     * Live SEO score (the EBQ-side counterpart to the editor's local
     * self-check). Composite of GSC rank + CTR + audit + cannibalization
     * + coverage breadth — see LiveSeoScoreService for the formula.
     *   GET /api/v1/posts/{externalPostId}/seo-score?url=...&focus_keyword=...
     */
    public function seoScore(Request $request, string $externalPostId, LiveSeoScoreService $service): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $url = (string) $request->query('url', '');
        $kw  = (string) $request->query('focus_keyword', '');

        // ISO 8601 GMT timestamp of the WP post's last edit. Used by the
        // service to detect "post updated since last audit" and re-queue.
        $postModifiedRaw = (string) $request->query('post_modified_at', '');
        $postModifiedAt = null;
        if ($postModifiedRaw !== '') {
            try {
                // Use Laravel's Carbon wrapper — matches the service's type hint.
                $postModifiedAt = \Illuminate\Support\Carbon::parse($postModifiedRaw);
            } catch (\Throwable) {
                $postModifiedAt = null;
            }
        }

        $payload = $service->score($website, $url, $kw !== '' ? $kw : null, $postModifiedAt);
        return response()->json([
            'external_post_id' => $externalPostId,
            'url' => $url,
            'focus_keyword' => $kw,
            // Tier is echoed on every response so the WP plugin's local
            // option stays in sync with EBQ-side billing changes — without
            // requiring the user to reconnect the site after upgrading.
            'tier' => $website->tier,
            'live' => $payload,
        ]);
    }

    /**
     * Topical-coverage gap analysis. Top-5 SERP via Serper + Mistral
     * extracts subtopics on both sides and returns missing ones.
     * Heavy operation — cached 7 days inside the service.
     *   POST /api/v1/posts/{externalPostId}/topical-gaps
     *   body: { url, focus_keyword, content }
     */
    public function topicalGaps(Request $request, string $externalPostId, TopicalGapService $service): JsonResponse
    {
        $website = $this->resolveWebsite($request);

        $data = $request->validate([
            'url' => 'required|string|max:2048',
            'focus_keyword' => 'required|string|min:2|max:200',
            'content' => 'required|string|min:200|max:120000',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|min:2|max:10',
        ]);

        $payload = $service->analyze(
            $website,
            (string) $data['focus_keyword'],
            (string) $data['content'],
            isset($data['country']) ? (string) $data['country'] : null,
            isset($data['language']) ? (string) $data['language'] : null,
        );

        return response()->json([
            'external_post_id' => $externalPostId,
            'url' => (string) $data['url'],
            'focus_keyword' => (string) $data['focus_keyword'],
            'gaps' => $payload,
        ]);
    }

    /**
     * AI title + meta description rewrites. Pro tier only — free tier
     * users see a 402 with `tier_required` so the plugin can render a
     * "Upgrade to Pro" CTA in place of the action button.
     *   POST /api/v1/posts/{externalPostId}/rewrite-snippet
     *   body: { focus_keyword, current_title, current_meta, content_excerpt, competitor_titles? }
     */
    public function rewriteSnippet(Request $request, string $externalPostId, AiSnippetRewriterService $service): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        if (! $website->isPro()) {
            return response()->json([
                'ok' => false,
                'error' => 'tier_required',
                'tier' => $website->tier,
                'required_tier' => Website::TIER_PRO,
                'message' => 'AI snippet rewrites are available on Pro. Upgrade to unlock.',
            ], 402);
        }

        $data = $request->validate([
            'focus_keyword' => 'required|string|min:2|max:200',
            'current_title' => 'nullable|string|max:200',
            'current_meta' => 'nullable|string|max:400',
            'content_excerpt' => 'required|string|min:50|max:8000',
            'competitor_titles' => 'nullable|array|max:5',
            'competitor_titles.*' => 'string|max:200',
        ]);

        $payload = $service->rewrite((int) $externalPostId, [
            'focus_keyword' => $data['focus_keyword'],
            'current_title' => $data['current_title'] ?? '',
            'current_meta' => $data['current_meta'] ?? '',
            'content_excerpt' => $data['content_excerpt'],
            'competitor_titles' => $data['competitor_titles'] ?? [],
        ]);

        return response()->json([
            'external_post_id' => $externalPostId,
            'tier' => $website->tier,
            'rewrite' => $payload,
        ]);
    }

    /**
     * AI content brief from a target keyword — subtopics, recommended
     * word count, suggested schema type, and internal-link targets from
     * the user's own site. Pro tier only.
     *   POST /api/v1/posts/{externalPostId}/content-brief
     *   body: { focus_keyword, country?, language? }
     */
    public function contentBrief(Request $request, string $externalPostId, AiContentBriefService $service): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        if (! $website->isPro()) {
            return response()->json([
                'ok' => false,
                'error' => 'tier_required',
                'tier' => $website->tier,
                'required_tier' => Website::TIER_PRO,
                'message' => 'AI content briefs are available on Pro. Upgrade to unlock.',
            ], 402);
        }

        $data = $request->validate([
            'focus_keyword' => 'required|string|min:2|max:200',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|min:2|max:10',
        ]);

        $payload = $service->brief($website, (int) $externalPostId, [
            'focus_keyword' => $data['focus_keyword'],
            'country' => $data['country'] ?? null,
            'language' => $data['language'] ?? null,
        ]);

        return response()->json([
            'external_post_id' => $externalPostId,
            'focus_keyword' => $data['focus_keyword'],
            'tier' => $website->tier,
            'brief' => $payload,
        ]);
    }

    /**
     * Receive a batch of 404 paths from the WP plugin's heartbeat. Each
     * unique path is queued for async LLM matching; we return immediately
     * with a count so the heartbeat stays fast. Idempotent — re-posting
     * the same path bumps `hits_30d` on the existing suggestion.
     *   POST /api/v1/posts/report-404s
     *   body: { paths: [{ path: "/foo/bar", hits: 12 }, ...] }
     */
    public function report404s(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);

        $data = $request->validate([
            'paths' => 'required|array|max:200',
            'paths.*.path' => 'required|string|max:700',
            'paths.*.hits' => 'nullable|integer|min:1|max:100000',
        ]);

        $queued = 0;
        foreach ($data['paths'] as $entry) {
            $path = (string) $entry['path'];
            $hits = (int) ($entry['hits'] ?? 1);
            if ($path === '') continue;
            MatchRedirectFor404Job::dispatch($website->id, $path, $hits);
            $queued++;
        }

        return response()->json([
            'ok' => true,
            'queued' => $queued,
        ]);
    }

    /**
     * List pending redirect suggestions for HQ to render. Filters to
     * `pending` by default; `?status=applied|rejected|all` overrides.
     *   GET /api/v1/redirect-suggestions?status=pending
     */
    public function redirectSuggestions(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $status = (string) $request->query('status', 'pending');

        $query = RedirectSuggestion::query()
            ->where('website_id', $website->id)
            ->orderByDesc('hits_30d')
            ->orderByDesc('confidence')
            ->orderByDesc('last_seen_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $rows = $query->limit(200)->get()->map(fn (RedirectSuggestion $s) => [
            'id' => $s->id,
            'source_path' => $s->source_path,
            'suggested_destination' => $s->suggested_destination,
            'confidence' => $s->confidence,
            'status' => $s->status,
            'rationale' => $s->rationale,
            'hits_30d' => $s->hits_30d,
            'last_seen_at' => $s->last_seen_at?->toIso8601String(),
            'matched_at' => $s->matched_at?->toIso8601String(),
        ])->all();

        return response()->json(['suggestions' => $rows]);
    }

    /**
     * User decision on a single suggestion. `apply` or `reject` is the
     * only valid action. After `apply` the WP plugin pulls and writes a
     * 301 rule; we record the decision so we don't re-suggest.
     *   POST /api/v1/redirect-suggestions/{id}/decide
     *   body: { action: "apply"|"reject" }
     */
    public function decideRedirectSuggestion(Request $request, int $id): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $data = $request->validate([
            'action' => 'required|in:apply,reject',
        ]);

        $suggestion = RedirectSuggestion::query()
            ->where('website_id', $website->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($data['action'] === 'apply') {
            if ($suggestion->suggested_destination === '') {
                return response()->json(['ok' => false, 'error' => 'no_destination'], 400);
            }
            $suggestion->status = RedirectSuggestion::STATUS_APPLIED;
            $suggestion->applied_at = Carbon::now();
        } else {
            $suggestion->status = RedirectSuggestion::STATUS_REJECTED;
        }
        $suggestion->save();

        return response()->json([
            'ok' => true,
            'id' => $suggestion->id,
            'status' => $suggestion->status,
        ]);
    }

    /**
     * Signed short-lived redirect into the EBQ /reports page.
     *   GET /api/v1/reports/iframe-url?insight=cannibalization
     */
    public function iframeUrl(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);
        $insight = (string) $request->query('insight', 'cannibalization');

        $signed = URL::temporarySignedRoute(
            'reports.index',
            Carbon::now()->addMinutes(5),
            ['insight' => $insight, 'website' => $website->id],
        );

        return response()->json([
            'url' => $signed,
            'expires_at' => Carbon::now()->addMinutes(5)->toIso8601String(),
        ]);
    }

    private function resolveWebsite(Request $request): Website
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website instanceof Website, 500, 'Website context missing');

        return $website;
    }
}
