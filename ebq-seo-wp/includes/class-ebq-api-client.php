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
        // anything under /api/v1/hq/ so the UI is always live. Editor-side
        // sidebar calls (per-post insights, suggestions) still benefit from
        // the 5-minute cache.
        $skip_cache = str_starts_with($path, '/api/v1/hq/');
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
     */
    private function request(string $method, string $path, array $body = []): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'error' => 'not_connected'];
        }

        $base = $this->base_url !== '' ? rtrim($this->base_url, '/') : self::base_url();
        $url = $base . $path;

        $args = [
            'method'  => $method,
            'timeout' => 12,
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
