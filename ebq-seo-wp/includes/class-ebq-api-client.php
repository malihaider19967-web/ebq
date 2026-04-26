<?php
/**
 * Thin wrapper around wp_remote_request that adds the plugin's bearer token.
 *
 * Base URL defaults to https://ebq.io. Self-hosted installs override by
 * defining the EBQ_API_BASE constant in wp-config.php — no UI field needed.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Api_Client
{
    public const DEFAULT_BASE = 'https://ebq.io';

    public function __construct(
        private readonly string $token,
        private readonly string $base_url = '',
    ) {}

    public static function base_url(): string
    {
        // 1. Constant in wp-config.php wins (for locked-down self-hosted installs).
        if (defined('EBQ_API_BASE') && is_string(EBQ_API_BASE) && EBQ_API_BASE !== '') {
            return rtrim((string) EBQ_API_BASE, '/');
        }

        // 2. Per-site override saved from the settings page.
        $override = (string) get_option('ebq_api_base_override', '');
        if ($override !== '') {
            return rtrim($override, '/');
        }

        return self::DEFAULT_BASE;
    }

    public function get_post_insights(string $post_id, string $canonical_url, ?string $target_keyword = null): array
    {
        $args = ['url' => $canonical_url];
        if ($target_keyword !== null && $target_keyword !== '') {
            $args['target_keyword'] = $target_keyword;
        }

        return $this->get(sprintf('/api/v1/posts/%s/insights', rawurlencode($post_id)), $args);
    }

    public function get_posts_bulk(array $urls): array
    {
        $urls = array_values(array_slice(array_filter(array_map('strval', $urls)), 0, 100));

        return $this->get('/api/v1/posts', ['urls' => $urls]);
    }

    public function get_dashboard(): array
    {
        return $this->get('/api/v1/dashboard');
    }

    public function get_iframe_url(string $insight): array
    {
        return $this->get('/api/v1/reports/iframe-url', ['insight' => $insight]);
    }

    public function get_focus_keyword_suggestions(string $post_id, string $canonical_url): array
    {
        return $this->get(
            sprintf('/api/v1/posts/%s/focus-keyword-suggestions', rawurlencode($post_id)),
            ['url' => $canonical_url]
        );
    }

    public function get_serp_preview(string $post_id, string $query): array
    {
        return $this->get(
            sprintf('/api/v1/posts/%s/serp-preview', rawurlencode($post_id)),
            ['query' => $query]
        );
    }

    public function get_related_keywords(string $post_id, string $keyword, string $url = ''): array
    {
        $args = ['keyword' => $keyword];
        if ($url !== '') {
            $args['url'] = $url;
        }

        return $this->get(
            sprintf('/api/v1/posts/%s/related-keywords', rawurlencode($post_id)),
            $args
        );
    }

    /**
     * Live SEO score — EBQ-side composite from real GSC data + audit
     * signals. Renders alongside the editor's local self-check.
     */
    public function get_seo_score(string $post_id, string $url, string $focus_keyword = '', string $post_modified_at = ''): array
    {
        $args = ['url' => $url];
        if ($focus_keyword !== '') {
            $args['focus_keyword'] = $focus_keyword;
        }
        if ($post_modified_at !== '') {
            // ISO 8601 timestamp of the post's last edit (GMT). Server
            // compares this against the latest PageAuditReport.audited_at
            // and re-queues the audit when the post was updated since.
            $args['post_modified_at'] = $post_modified_at;
        }
        return $this->get(
            sprintf('/api/v1/posts/%s/seo-score', rawurlencode($post_id)),
            $args
        );
    }

    /**
     * Topical-coverage gap analysis. Sends the post body so EBQ can
     * compare what the user wrote against what the top SERP results
     * cover. POST (not GET) because content can be large.
     */
    /**
     * AI snippet rewriter — Pro tier only on the EBQ side. Returns 3
     * ranked title + meta rewrites with rationales, or a 402-tier_required
     * payload that the plugin renders as an upgrade CTA.
     */
    public function ai_rewrite_snippet(string $post_id, string $focus_keyword, string $current_title, string $current_meta, string $content_excerpt, array $competitor_titles = [], string $intent = ''): array
    {
        $body = [
            'focus_keyword' => $focus_keyword,
            'current_title' => $current_title,
            'current_meta' => $current_meta,
            'content_excerpt' => $content_excerpt,
            'competitor_titles' => array_values(array_slice(array_filter(array_map('strval', $competitor_titles)), 0, 5)),
        ];
        if ($intent !== '') {
            $body['intent'] = $intent;
        }
        // Snippet rewrite is one Mistral call (~12–18s warm, up to ~30s cold);
        // 45s gives comfortable headroom while still failing fast.
        return $this->request('POST', sprintf('/api/v1/posts/%s/rewrite-snippet', rawurlencode($post_id)), $body, 45);
    }

    /** Intent registry — used by the picker UI in the editor sidebar. */
    public function ai_rewrite_intents(): array
    {
        return $this->get('/api/v1/posts/rewrite-intents', []);
    }

    /**
     * AI content brief from a target keyword — Pro tier only on EBQ.
     * Returns subtopics, recommended word count, schema type, outline,
     * and internal-link targets pulled from this site's GSC footprint.
     */
    /**
     * Ship a batch of 404 paths to EBQ for AI-suggested redirect matching.
     * Called by EBQ_404_Tracker on the hourly cron drain.
     *
     * @param  list<array{path: string, hits: int}>  $paths
     */
    public function report_404s(array $paths): array
    {
        return $this->request('POST', '/api/v1/posts/report-404s', ['paths' => $paths]);
    }

    /**
     * Pull the current redirect-suggestion list (pending / applied / rejected).
     * Used by the HQ admin tab.
     */
    public function get_redirect_suggestions(string $status = 'pending'): array
    {
        $args = ['status' => $status];
        return $this->get('/api/v1/redirect-suggestions', $args);
    }

    /**
     * Apply or reject a single redirect suggestion. On 'apply' the WP-side
     * caller should ALSO write the rule into EBQ_Redirects so it's served
     * locally — the EBQ status flip just records the decision so we won't
     * re-suggest.
     */
    public function decide_redirect_suggestion(int $id, string $action): array
    {
        return $this->request('POST', sprintf('/api/v1/redirect-suggestions/%d/decide', $id), ['action' => $action]);
    }

    /* ─── Phase 3 ──────────────────────────────────────────── */

    public function hq_serp_features(int $days = 30): array
    {
        return $this->get('/api/v1/hq/serp-features', ['days' => $days]);
    }

    public function hq_benchmarks(string $country = ''): array
    {
        $args = $country !== '' ? ['country' => $country] : [];
        return $this->get('/api/v1/hq/benchmarks', $args);
    }

    public function hq_backlink_prospects(array $competitors): array
    {
        return $this->request('POST', '/api/v1/hq/backlink-prospects', [
            'competitors' => array_values(array_slice(array_filter(array_map('strval', $competitors)), 0, 20)),
        ]);
    }

    public function hq_backlink_prospects_draft(array $body): array
    {
        return $this->request('POST', '/api/v1/hq/backlink-prospects/draft', $body);
    }

    public function hq_topical_authority(): array
    {
        return $this->get('/api/v1/hq/topical-authority', []);
    }

    public function hq_outreach_prospects_list(string $status = ''): array
    {
        $args = $status !== '' ? ['status' => $status] : [];
        return $this->get('/api/v1/hq/outreach-prospects', $args);
    }

    public function hq_outreach_prospects_update(int $id, array $patch): array
    {
        return $this->request('POST', sprintf('/api/v1/hq/outreach-prospects/%d', $id), $patch);
    }

    public function hq_outreach_prospects_auto_discover(int $days = 30): array
    {
        return $this->request('POST', '/api/v1/hq/outreach-prospects/auto-discover?days=' . $days);
    }

    public function entity_coverage(string $post_id, string $url, bool $check_only = false): array
    {
        $args = ['url' => $url];
        if ($check_only) $args['check_only'] = '1';
        return $this->get(sprintf('/api/v1/posts/%s/entity-coverage', rawurlencode($post_id)), $args);
    }

    public function ai_writer(string $post_id, string $focus_keyword, string $current_html, string $url = '', array $wp_pages = [], string $country = '', string $language = '', ?array $selected = null): array
    {
        $body = ['focus_keyword' => $focus_keyword];
        if ($current_html !== '') $body['current_html'] = $current_html;
        if ($url !== '')          $body['url']          = $url;
        if (! empty($wp_pages))   $body['wp_pages']     = array_values($wp_pages);
        if ($country !== '')      $body['country']      = $country;
        if ($language !== '')     $body['language']     = $language;
        if (is_array($selected) && ! empty($selected)) $body['selected'] = $selected;
        // Cold path: Serper + brief LLM (~45s) + topical-gaps LLM (~30s) +
        // writer LLM (up to 240s for 20 sections at 16k tokens). 280s
        // total leaves a small buffer under the 300s clamp ceiling.
        return $this->request('POST', sprintf('/api/v1/posts/%s/ai-writer', rawurlencode($post_id)), $body, 280);
    }

    public function ai_writer_plan(string $post_id, string $focus_keyword, string $current_html, string $country = '', string $language = ''): array
    {
        $body = ['focus_keyword' => $focus_keyword];
        if ($current_html !== '') $body['current_html'] = $current_html;
        if ($country !== '')      $body['country']      = $country;
        if ($language !== '')     $body['language']     = $language;
        // Brief LLM (~45s) + topical-gaps LLM (~30s) cold = ~90s; cached ≈ instant.
        return $this->request('POST', sprintf('/api/v1/posts/%s/ai-writer/plan', rawurlencode($post_id)), $body, 120);
    }

    public function ai_content_brief(string $post_id, string $focus_keyword, string $country = '', string $language = ''): array
    {
        $body = ['focus_keyword' => $focus_keyword];
        if ($country !== '')  $body['country'] = $country;
        if ($language !== '') $body['language'] = $language;
        // Brief = Serper top-10 SERP (≤30s) + Mistral JSON (45s) + GSC join.
        // 80s covers the cold path; warm cache returns in <500ms.
        return $this->request('POST', sprintf('/api/v1/posts/%s/content-brief', rawurlencode($post_id)), $body, 80);
    }

    public function get_topical_gaps(string $post_id, string $url, string $focus_keyword, string $content, string $country = '', string $language = ''): array
    {
        $body = [
            'url' => $url,
            'focus_keyword' => $focus_keyword,
            'content' => $content,
        ];
        if ($country !== '')  $body['country'] = $country;
        if ($language !== '') $body['language'] = $language;

        // Topical-gaps = Serper top-5 SERP + 2 Mistral JSON calls (extract +
        // compare). Cold path runs 40–60s; cached calls return immediately.
        return $this->request(
            'POST',
            sprintf('/api/v1/posts/%s/topical-gaps', rawurlencode($post_id)),
            $body,
            80
        );
    }

    public function get_internal_link_suggestions(string $post_id, string $url, string $keyword = '', string $title = ''): array
    {
        $args = ['url' => $url];
        if ($keyword !== '') {
            $args['keyword'] = $keyword;
        }
        if ($title !== '') {
            $args['title'] = $title;
        }

        return $this->get(
            sprintf('/api/v1/posts/%s/internal-link-suggestions', rawurlencode($post_id)),
            $args
        );
    }

    /* ─── EBQ HQ — top-level admin dashboard ─────────────────── */

    public function hq_overview(string $range = '30d'): array
    {
        return $this->get('/api/v1/hq/overview', ['range' => $range]);
    }

    public function hq_performance(string $range = '30d'): array
    {
        return $this->get('/api/v1/hq/performance', ['range' => $range]);
    }

    public function hq_keywords(array $args = []): array
    {
        return $this->get('/api/v1/hq/keywords', $args);
    }

    public function hq_keyword_history(int $id): array
    {
        return $this->get(sprintf('/api/v1/hq/keywords/%d/history', $id));
    }

    public function hq_keyword_candidates(int $limit = 25): array
    {
        return $this->get('/api/v1/hq/keywords/candidates', ['limit' => $limit]);
    }

    public function hq_gsc_keywords(array $args = []): array
    {
        return $this->get('/api/v1/hq/gsc-keywords', $args);
    }

    public function hq_create_keyword(array $payload): array
    {
        return $this->request('POST', '/api/v1/hq/keywords', $payload);
    }

    public function hq_update_keyword(int $id, array $payload): array
    {
        return $this->request('PATCH', sprintf('/api/v1/hq/keywords/%d', $id), $payload);
    }

    public function hq_delete_keyword(int $id): array
    {
        return $this->request('DELETE', sprintf('/api/v1/hq/keywords/%d', $id));
    }

    public function hq_recheck_keyword(int $id): array
    {
        return $this->request('POST', sprintf('/api/v1/hq/keywords/%d/recheck', $id));
    }

    public function hq_pages(array $args = []): array
    {
        return $this->get('/api/v1/hq/pages', $args);
    }

    public function hq_index_status(array $args = []): array
    {
        return $this->get('/api/v1/hq/index-status', $args);
    }

    public function hq_index_status_submit(string $url): array
    {
        return $this->request('POST', '/api/v1/hq/index-status/submit', ['url' => $url]);
    }

    public function hq_insights(string $type, int $limit = 25): array
    {
        return $this->get(sprintf('/api/v1/hq/insights/%s', rawurlencode($type)), ['limit' => $limit]);
    }

    private function get(string $path, array $query = []): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'error' => 'not_connected'];
        }

        $base = $this->base_url !== '' ? rtrim($this->base_url, '/') : self::base_url();
        $url = $base . $path;
        if (! empty($query)) {
            $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
        }

        // EBQ HQ admin endpoints are user-driven dashboards — cache hides
        // newly-added keywords / fresh syncs and breaks the "I clicked Save,
        // why didn't it appear?" expectation. Skip the transient layer for
        // anything under /api/v1/hq/ so the UI is always live.
        //
        // Also skip for endpoints whose response payload itself reports a
        // queue/audit state (seo-score, topical-gaps). These flip from
        // `queued` → `running` → `ready` over a span of 30–90s; caching the
        // first response would pin "refreshing" in the editor for up to 5
        // minutes even after the upstream audit finishes — which was the
        // actual cause of the "stuck on re-auditing" symptom (LSCache was
        // a red herring; the stale data lived in WP transients all along).
        $skip_cache = str_starts_with($path, '/api/v1/hq/')
            || str_contains($path, '/seo-score')
            || str_contains($path, '/topical-gaps')
            || str_contains($path, '/entity-coverage');
        // Cache key is namespaced by the current version (incremented on
        // every write). This invalidates ALL cached entries atomically
        // regardless of where the cache backend stores them — safe across
        // database transients, Redis, Memcache, etc.
        $cache_key = $skip_cache ? null : ('ebq_api_v' . self::cache_version() . '_' . md5($url));

        if ($cache_key !== null) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'User-Agent' => 'EBQ-SEO-WP/' . EBQ_SEO_VERSION . '; ' . home_url(),
            ],
        ]);
        return $this->handle_response($response, $cache_key);
    }

    /**
     * Non-GET requests for the HQ admin (POST keyword create, PATCH/DELETE,
     * recheck). Bypasses the read-cache and busts every cached entry on the
     * mutated namespace so the next GET reflects the change immediately.
     *
     * `$timeout` is per-call so AI endpoints (which may chain a Serper call
     * + an LLM call server-side and take 30–60s on a cold cache) don't get
     * killed by the snappy default that other mutating routes rely on.
     */
    private function request(string $method, string $path, array $body = [], int $timeout = 12): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'error' => 'not_connected'];
        }

        $base = $this->base_url !== '' ? rtrim($this->base_url, '/') : self::base_url();
        $url = $base . $path;

        $args = [
            'method'  => $method,
            // Ceiling 300s (was 120s). The AI Writer's worst-case end-to-end
            // (Serper + brief LLM + topical-gaps LLM + 20-section writer LLM)
            // approaches 4 minutes on a fully cold cache. Per-call sites
            // pass their own value; this just guards against runaway.
            'timeout' => max(5, min(300, $timeout)),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'EBQ-SEO-WP/' . EBQ_SEO_VERSION . '; ' . home_url(),
            ],
        ];
        if (! empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        $decoded = $this->handle_response($response, null);

        // Mutating call — bump the cache version so every previously-cached
        // GET becomes orphaned. Works on any cache backend (db transients,
        // Redis, Memcache) because we just change the namespace, not delete.
        self::bump_cache_version();

        return $decoded;
    }

    private function handle_response($response, ?string $cache_key): array
    {

        if (is_wp_error($response)) {
            // NOT cached — transient errors should not stick.
            return ['ok' => false, 'error' => 'network_error', 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 400 || ! is_array($decoded)) {
            // NOT cached — surfacing errors for retry rather than poisoning.
            return ['ok' => false, 'error' => 'http_' . $code, 'body' => is_string($body) ? mb_substr($body, 0, 500) : ''];
        }

        // Auto-sync subscription tier from any response that carries it.
        // This means the editor picks up upgrades / downgrades the next time
        // it hits ANY endpoint — no reconnect required. Whitelist values so
        // a malformed payload can't poison the option.
        if (isset($decoded['tier']) && is_string($decoded['tier'])) {
            $tier = strtolower(trim($decoded['tier']));
            if (in_array($tier, ['free', 'pro'], true) && $tier !== (string) get_option('ebq_site_tier', 'free')) {
                update_option('ebq_site_tier', $tier);
            }
        }

        // Cache only useful, complete responses. Never cache:
        //   - Explicit failures (`ok: false`)
        //   - Responses carrying a `diagnostic` flag (means the upstream
        //     hit an empty / mismatched state we may resolve next call)
        //   - Empty `suggestions` arrays (lets retries work without waiting
        //     5 minutes for the cache to expire)
        $shouldCache = $cache_key !== null;
        if (isset($decoded['ok']) && $decoded['ok'] === false) {
            $shouldCache = false;
        } elseif (isset($decoded['diagnostic']) && $decoded['diagnostic'] !== null && $decoded['diagnostic'] !== '') {
            $shouldCache = false;
        } elseif (array_key_exists('suggestions', $decoded) && empty($decoded['suggestions'])) {
            $shouldCache = false;
        }

        if ($shouldCache && $cache_key !== null) {
            set_transient($cache_key, $decoded, 5 * MINUTE_IN_SECONDS);
        }

        return $decoded;
    }

    /**
     * Current cache namespace version. Cache keys include this so bumping
     * it invalidates every cached entry at once — no need to enumerate
     * keys, no LIKE queries, works on any cache backend (object cache,
     * Redis, Memcache, db transients).
     */
    public static function cache_version(): int
    {
        $v = (int) get_option('ebq_api_cache_v', 1);
        return $v > 0 ? $v : 1;
    }

    /**
     * Increment the cache namespace. Old entries become orphaned and expire
     * naturally via the 5-minute TTL. Called automatically after every
     * write (POST/PATCH/DELETE) and from the settings page "Refresh data"
     * button.
     */
    public static function bump_cache_version(): int
    {
        $next = self::cache_version() + 1;
        // autoload=true so the bump propagates instantly across all PHP
        // requests on this site without a follow-up query.
        update_option('ebq_api_cache_v', $next, true);
        return $next;
    }

    /**
     * Backwards-compatible alias kept for any code (e.g. settings page) that
     * still calls the old name. Cache versioning is the real mechanism now.
     */
    public static function clear_response_cache(): int
    {
        self::bump_cache_version();
        return 0;
    }
}
