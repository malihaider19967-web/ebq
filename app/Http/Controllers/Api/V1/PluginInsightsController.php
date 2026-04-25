<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\PluginInsightResolver;
use App\Services\ReportDataService;
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
