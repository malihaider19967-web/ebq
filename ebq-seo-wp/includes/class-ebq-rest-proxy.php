<?php
/**
 * REST proxy: /wp-json/ebq/v1/* — the Gutenberg React sidebar calls these
 * (authenticated via WP cookies), and this class forwards them to EBQ using
 * the site's API token. This keeps the token out of browser JS.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Rest_Proxy
{
    /**
     * Routes whose responses are dynamic (live SEO score, audit state,
     * GSC suggestions, anything that changes on every save) and must
     * NEVER be served from any cache layer — browser, Cloudflare, nginx
     * fastcgi_cache, LiteSpeed Cache.
     *
     * Listed as path prefixes (without `/wp-json`) so dynamic-id routes
     * like `/ebq/v1/seo-score/123` match without enumerating each ID.
     *
     * Static-ish routes (e.g. `/ebq/v1/dashboard-html`) are deliberately
     * NOT in this list — they're allowed to ride normal caching policy.
     * That's the point of the "scope to my plugin endpoint" requirement:
     * we only opt out of caching where it matters, not site-wide.
     *
     * @var list<string>
     */
    private const NEVER_CACHE_ROUTE_PREFIXES = [
        '/ebq/v1/seo-score',
        '/ebq/v1/topical-gaps',
        '/ebq/v1/post-insights',
        '/ebq/v1/focus-keyword-suggestions',
        '/ebq/v1/related-keywords',
        '/ebq/v1/serp-preview',
        '/ebq/v1/internal-link-suggestions',
        '/ebq/v1/track-keyword',
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);

        // rest_pre_dispatch fires BEFORE the route handler runs — early
        // enough that DONOTCACHEPAGE / LiteSpeed control actions take effect
        // before any cache layer makes its store-or-skip decision.
        add_filter('rest_pre_dispatch', [$this, 'maybe_disable_cache_for_dynamic_routes'], 10, 3);

        // rest_post_dispatch fires AFTER the response is built — the right
        // place to attach Cache-Control / Pragma / Expires / vendor headers.
        add_filter('rest_post_dispatch', [$this, 'maybe_apply_nocache_headers'], 10, 3);
    }

    public function register_routes(): void
    {
        register_rest_route('ebq/v1', '/post-insights/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'post_insights'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
            ],
        ]);

        register_rest_route('ebq/v1', '/dashboard', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'dashboard'],
        ]);

        register_rest_route('ebq/v1', '/focus-keyword-suggestions/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'focus_keyword_suggestions'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
            ],
        ]);

        register_rest_route('ebq/v1', '/serp-preview/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'serp_preview'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
                'query' => ['required' => true],
            ],
        ]);

        register_rest_route('ebq/v1', '/internal-link-suggestions/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'internal_link_suggestions'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
            ],
        ]);

        register_rest_route('ebq/v1', '/related-keywords/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'related_keywords'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
                'keyword' => ['required' => true],
            ],
        ]);

        // POST (not GET) so no cache layer (browser, CDN, LiteSpeed
        // page-cache) ever stores the response. The header-based opt-outs
        // were not enough on real LiteSpeed installs — switching the
        // method is the bulletproof fix because POST is unconditionally
        // never cached. Also accepts GET for back-compat with older plugin
        // builds still in the wild.
        register_rest_route('ebq/v1', '/seo-score/(?P<id>\d+)', [
            'methods' => ['GET', 'POST'],
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'seo_score'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
            ],
        ]);

        register_rest_route('ebq/v1', '/topical-gaps/(?P<id>\d+)', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'topical_gaps'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
            ],
        ]);

        register_rest_route('ebq/v1', '/post-insights-html/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'post_insights_html'],
            'args' => [
                'id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0],
            ],
        ]);

        register_rest_route('ebq/v1', '/dashboard-html', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'dashboard_html'],
        ]);

        register_rest_route('ebq/v1', '/bulk-post-insights', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'bulk_post_insights'],
            'args' => [
                'post_ids' => ['required' => true],
            ],
        ]);

        // EBQ HQ — top-level admin dashboard data. Same auth model as the
        // other proxy routes; only `manage_options` users see the menu.
        register_rest_route('ebq/v1', '/hq/overview', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_overview'],
        ]);
        register_rest_route('ebq/v1', '/hq/performance', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_performance'],
        ]);
        register_rest_route('ebq/v1', '/hq/keywords', [
            [
                'methods' => 'GET',
                'permission_callback' => [$this, 'can_view_hq'],
                'callback' => [$this, 'hq_keywords'],
            ],
            [
                'methods' => 'POST',
                'permission_callback' => [$this, 'can_view_hq'],
                'callback' => [$this, 'hq_create_keyword'],
            ],
        ]);
        register_rest_route('ebq/v1', '/hq/keywords/candidates', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_keyword_candidates'],
        ]);
        register_rest_route('ebq/v1', '/hq/keywords/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'permission_callback' => [$this, 'can_view_hq'],
                'callback' => [$this, 'hq_update_keyword'],
                'args' => ['id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0]],
            ],
            [
                'methods' => 'DELETE',
                'permission_callback' => [$this, 'can_view_hq'],
                'callback' => [$this, 'hq_delete_keyword'],
                'args' => ['id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0]],
            ],
        ]);
        register_rest_route('ebq/v1', '/hq/keywords/(?P<id>\d+)/recheck', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_recheck_keyword'],
            'args' => ['id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0]],
        ]);
        register_rest_route('ebq/v1', '/hq/keywords/(?P<id>\d+)/history', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_keyword_history'],
            'args' => ['id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0]],
        ]);
        register_rest_route('ebq/v1', '/hq/gsc-keywords', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_gsc_keywords'],
        ]);
        register_rest_route('ebq/v1', '/hq/pages', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_pages'],
        ]);
        register_rest_route('ebq/v1', '/hq/index-status', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_index_status'],
        ]);
        register_rest_route('ebq/v1', '/hq/insights/(?P<type>[a-z_]+)', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_insights'],
            'args' => ['type' => ['validate_callback' => static fn ($v): bool => is_string($v) && preg_match('/^[a-z_]+$/', $v) === 1]],
        ]);
        register_rest_route('ebq/v1', '/hq/iframe-url', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_iframe_url'],
        ]);

        // Editor-friendly shortcut: any user with edit_posts can promote a
        // keyword to the Rank Tracker. Used by the Gutenberg sidebar's
        // "+ Track" button next to the focus keyphrase, and any other
        // surface where requiring `manage_options` would block the action.
        register_rest_route('ebq/v1', '/track-keyword', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_edit'],
            'callback' => [$this, 'track_keyword'],
        ]);

        // Migration progress poller — settings card hits this every 2 s
        // while a background migration is running.
        register_rest_route('ebq/v1', '/migration/status', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'migration_status'],
        ]);
    }

    public function can_edit(): bool
    {
        return current_user_can('edit_posts');
    }

    public function can_view_hq(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Wrap a payload in a WP_REST_Response with aggressive no-cache headers.
     * Defeats LiteSpeed Cache, Cloudflare, browser cache, and any other
     * layer between WP and the React app. HQ data is admin-only and
     * mutates often — never appropriate to cache anywhere.
     */
    private function hq_response($data, int $status = 200): WP_REST_Response
    {
        $response = new WP_REST_Response($data, $status);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        // LiteSpeed-specific: stops LSCache from storing this response.
        $response->header('X-LiteSpeed-Cache-Control', 'no-cache');
        return $response;
    }

    public function post_insights(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $url = get_permalink($post_id);
        if (! $url) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        $target_keyword = (string) ($request->get_param('target_keyword') ?: '');
        $payload = EBQ_Plugin::api_client()->get_post_insights((string) $post_id, $url, $target_keyword ?: null);

        return new WP_REST_Response($payload, 200);
    }

    public function dashboard(): WP_REST_Response
    {
        return new WP_REST_Response(EBQ_Plugin::api_client()->get_dashboard(), 200);
    }

    public function focus_keyword_suggestions(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $url = get_permalink($post_id);
        if (! $url) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        return new WP_REST_Response(
            EBQ_Plugin::api_client()->get_focus_keyword_suggestions((string) $post_id, $url),
            200
        );
    }

    public function serp_preview(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $query = (string) $request->get_param('query');
        if ($query === '') {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_query'], 400);
        }

        return new WP_REST_Response(
            EBQ_Plugin::api_client()->get_serp_preview((string) $post_id, $query),
            200
        );
    }

    public function related_keywords(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $keyword = trim((string) $request->get_param('keyword'));
        if ($keyword === '') {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_keyword'], 400);
        }

        $url = (string) get_permalink($post_id);

        return new WP_REST_Response(
            EBQ_Plugin::api_client()->get_related_keywords((string) $post_id, $keyword, $url),
            200
        );
    }

    /**
     * Live EBQ-side SEO score for the post URL. Optional focus keyword
     * pulled from post meta (the user's saved focus) so the score is
     * computed against the same keyword the editor sidebar shows.
     */
    public function seo_score(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $url = get_permalink($post_id);
        if (! $url) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        // Allow query-param override; otherwise use the post's saved
        // focus keyword so the live score matches what the user is
        // actively optimizing for.
        $focus = (string) ($request->get_param('focus_keyword') ?: get_post_meta($post_id, '_ebq_focus_keyword', true));

        // post_modified_gmt lets the server compare against the latest
        // audit's audited_at and re-queue an audit when the post was
        // updated after the last run. Sourced server-side so the React
        // client doesn't have to track it.
        //
        // We MUST use get_post_modified_time('c', true) instead of
        // mysql2date('c', $post_modified_gmt). Reason: mysql2date parses
        // datetime strings using wp_timezone() — which means a GMT-stored
        // value gets re-interpreted as the site's local time, producing a
        // timestamp that's hours off. get_post_modified_time correctly
        // builds a UTC DateTime and formats with offset (e.g. "+00:00")
        // so Carbon::parse() on the EBQ side reads the actual instant.
        $post = get_post($post_id);
        $modified = '';
        if ($post && $post->post_modified_gmt && $post->post_modified_gmt !== '0000-00-00 00:00:00') {
            $candidate = get_post_modified_time('c', true, $post, false);
            if (is_string($candidate) && $candidate !== '') {
                $modified = $candidate;
            }
        }

        return new WP_REST_Response(
            EBQ_Plugin::api_client()->get_seo_score((string) $post_id, $url, $focus, $modified),
            200
        );
        // Headers + cache-bust are applied by maybe_apply_nocache_headers()
        // via the rest_post_dispatch filter — see register().
    }

    /* ─── Cache hardening for dynamic plugin endpoints ─────────────
     *
     * Two filter callbacks + one matcher. The `register()` method wires:
     *   - rest_pre_dispatch  → maybe_disable_cache_for_dynamic_routes()
     *     fires BEFORE the handler so DONOTCACHEPAGE / LiteSpeed control
     *     actions are set early enough for the cache layer's store decision.
     *   - rest_post_dispatch → maybe_apply_nocache_headers()
     *     fires AFTER the response is built, the only place to attach
     *     Cache-Control / Pragma / Expires / vendor headers to a
     *     WP_REST_Response.
     *
     * Both callbacks check `is_dynamic_route()`, so this only affects the
     * specific plugin endpoints that need it — never anyone else's routes.
     * ────────────────────────────────────────────────────────────── */

    /**
     * Returns true when the request targets a dynamic ebq endpoint that
     * must never be served from a cache. Match is by route-path PREFIX so
     * `/ebq/v1/seo-score/123` matches `'/ebq/v1/seo-score'`.
     */
    private function is_dynamic_route(WP_REST_Request $request): bool
    {
        $route = (string) $request->get_route();
        if ($route === '') {
            return false;
        }
        foreach (self::NEVER_CACHE_ROUTE_PREFIXES as $prefix) {
            if ($route === $prefix || strpos($route, $prefix . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * rest_pre_dispatch callback. Sets the constants + LiteSpeed control
     * actions BEFORE the handler runs — the only window where they reliably
     * influence the cache layer's store decision.
     *
     * @param  mixed             $result   Existing pre-dispatch result (null = continue).
     * @param  WP_REST_Server    $server   Unused.
     * @param  WP_REST_Request   $request  The incoming REST request.
     * @return mixed Pass-through; we never short-circuit dispatch.
     */
    public function maybe_disable_cache_for_dynamic_routes($result, $server, $request)
    {
        if (! ($request instanceof WP_REST_Request) || ! $this->is_dynamic_route($request)) {
            return $result;
        }

        // Generic WP page-cache plugins (W3TC, WP Rocket, WP Super Cache,
        // Cachify, Comet Cache, Hummingbird) all check these constants and
        // bail when truthy. Only set if not already defined — defining twice
        // raises a notice on some PHP setups.
        if (! defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE', true);
        if (! defined('DONOTCACHEDB'))     define('DONOTCACHEDB', true);
        if (! defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);

        // LiteSpeed Cache: the X-LiteSpeed-Cache-Control header alone is
        // unreliable when "Cache REST API" is enabled. The official action
        // hooks are the only deterministic opt-out — they run before LSCache
        // makes its store decision and don't depend on settings.
        do_action('litespeed_control_set_nocache', 'EBQ dynamic plugin endpoint');
        do_action('litespeed_control_set_private', 'EBQ dynamic plugin endpoint is per-user');

        return $result;
    }

    /**
     * rest_post_dispatch callback. Attaches the full layered set of no-cache
     * headers so every common cache layer (browser, Cloudflare, nginx,
     * LiteSpeed) backs off via at least one signal it understands.
     *
     * @param  WP_HTTP_Response  $response  The dispatched response.
     * @param  WP_REST_Server    $server    Unused.
     * @param  WP_REST_Request   $request   The incoming REST request.
     * @return WP_HTTP_Response Same response, possibly with headers added.
     */
    public function maybe_apply_nocache_headers($response, $server, $request)
    {
        if (! ($response instanceof WP_HTTP_Response)) {
            return $response;
        }
        if (! ($request instanceof WP_REST_Request) || ! $this->is_dynamic_route($request)) {
            return $response;
        }

        // Browser + standards-compliant proxies.
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        // nginx fastcgi_cache / proxy_cache honor this even when other
        // headers are stripped or rewritten upstream.
        $response->header('X-Accel-Expires', '0');

        // Cloudflare APO + Workers honor these (cdn-cache-control is the
        // formal IETF draft header for split client/CDN policies).
        $response->header('CDN-Cache-Control', 'no-store');
        $response->header('Cloudflare-CDN-Cache-Control', 'no-store');

        // LiteSpeed Cache reads this header on top of the action hooks
        // we already fired in rest_pre_dispatch. Belt-and-suspenders.
        $response->header('X-LiteSpeed-Cache-Control', 'no-cache');

        return $response;
    }

    /**
     * Topical gap analysis. Sends post content to EBQ which scrapes
     * top-5 competitors and asks Mistral to extract subtopics. The
     * 7-day cache lives on the EBQ side; here we just proxy.
     */
    public function topical_gaps(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $url = get_permalink($post_id);
        if (! $url) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        $body = $request->get_json_params();
        if (! is_array($body)) $body = $request->get_params();

        $focus = trim((string) ($body['focus_keyword'] ?? get_post_meta($post_id, '_ebq_focus_keyword', true)));
        $content = (string) ($body['content'] ?? '');
        if ($focus === '' || $content === '') {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_focus_or_content'], 400);
        }

        $country  = (string) ($body['country']  ?? '');
        $language = (string) ($body['language'] ?? '');

        $response = new WP_REST_Response(
            EBQ_Plugin::api_client()->get_topical_gaps((string) $post_id, $url, $focus, $content, $country, $language),
            200
        );
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        return $response;
    }

    public function internal_link_suggestions(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $url = get_permalink($post_id);
        if (! $url) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        $keyword = (string) get_post_meta($post_id, '_ebq_focus_keyword', true);
        $title   = (string) get_the_title($post_id);

        $payload = EBQ_Plugin::api_client()->get_internal_link_suggestions((string) $post_id, $url, $keyword, $title);
        if (! is_array($payload) || ($payload['ok'] ?? null) === false) {
            return new WP_REST_Response($payload, 200);
        }

        // Decorate each suggestion with the WP-side post title (Laravel only
        // knows the URL).
        $suggestions = isset($payload['suggestions']) && is_array($payload['suggestions']) ? $payload['suggestions'] : [];
        foreach ($suggestions as &$row) {
            if (! is_array($row) || empty($row['url'])) {
                continue;
            }
            $row['title'] = $this->resolve_title_from_url((string) $row['url']);
        }
        unset($row);
        $payload['suggestions'] = $suggestions;

        return new WP_REST_Response($payload, 200);
    }

    private function resolve_title_from_url(string $url): string
    {
        $resolved_id = url_to_postid($url);
        if ($resolved_id > 0) {
            $title = (string) get_the_title($resolved_id);
            if ($title !== '') {
                return $title;
            }
        }

        // Fallback: derive a friendly label from the path.
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $url;
        }
        $segments = array_values(array_filter(explode('/', $path)));
        $last = $segments ? end($segments) : '';

        return $last !== '' ? str_replace(['-', '_'], ' ', urldecode((string) $last)) : $url;
    }

    public function post_insights_html(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $url = get_permalink($post_id);
        if (! $url) {
            return new WP_REST_Response(['ok' => false, 'error' => 'post_not_found'], 404);
        }

        $data = EBQ_Plugin::api_client()->get_post_insights((string) $post_id, $url);
        if (empty($data) || ! is_array($data) || ($data['ok'] ?? null) === false) {
            $reason = is_array($data) ? (string) ($data['error'] ?? 'unknown') : 'no_response';

            return new WP_REST_Response(['ok' => false, 'error' => $reason], 200);
        }

        return new WP_REST_Response([
            'ok' => true,
            'html' => EBQ_Meta_Box::render_content($data),
        ], 200);
    }

    public function dashboard_html(): WP_REST_Response
    {
        $data = EBQ_Plugin::api_client()->get_dashboard();
        if (empty($data) || ! is_array($data) || ($data['ok'] ?? null) === false) {
            $reason = is_array($data) ? (string) ($data['error'] ?? 'unknown') : 'no_response';

            return new WP_REST_Response(['ok' => false, 'error' => $reason], 200);
        }

        return new WP_REST_Response([
            'ok' => true,
            'html' => EBQ_Dashboard_Widget::render_content($data),
        ], 200);
    }

    /* ─── HQ proxy methods ───────────────────────────────── */

    public function hq_overview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->hq_response(EBQ_Plugin::api_client()->hq_overview((string) ($request->get_param('range') ?: '30d')), 200);
    }

    public function hq_performance(WP_REST_Request $request): WP_REST_Response
    {
        return $this->hq_response(EBQ_Plugin::api_client()->hq_performance((string) ($request->get_param('range') ?: '30d')), 200);
    }

    public function hq_keywords(WP_REST_Request $request): WP_REST_Response
    {
        $args = array_filter([
            'sort' => (string) $request->get_param('sort'),
            'dir' => (string) $request->get_param('dir'),
            'page' => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('per_page') ?: 25),
            'search' => (string) ($request->get_param('search') ?: ''),
        ], static fn ($v) => $v !== '' && $v !== 0);
        return $this->hq_response(EBQ_Plugin::api_client()->hq_keywords($args), 200);
    }

    public function hq_keyword_history(WP_REST_Request $request): WP_REST_Response
    {
        return $this->hq_response(EBQ_Plugin::api_client()->hq_keyword_history((int) $request->get_param('id')), 200);
    }

    public function hq_keyword_candidates(WP_REST_Request $request): WP_REST_Response
    {
        $limit = max(10, min(100, (int) ($request->get_param('limit') ?: 25)));
        return $this->hq_response(EBQ_Plugin::api_client()->hq_keyword_candidates($limit), 200);
    }

    public function hq_create_keyword(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $this->keyword_payload_from_request($request);
        return $this->hq_response(EBQ_Plugin::api_client()->hq_create_keyword($payload), 200);
    }

    public function hq_update_keyword(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $this->keyword_payload_from_request($request, /*for_update*/ true);
        return $this->hq_response(EBQ_Plugin::api_client()->hq_update_keyword((int) $request->get_param('id'), $payload), 200);
    }

    public function hq_delete_keyword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->hq_response(EBQ_Plugin::api_client()->hq_delete_keyword((int) $request->get_param('id')), 200);
    }

    public function hq_recheck_keyword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->hq_response(EBQ_Plugin::api_client()->hq_recheck_keyword((int) $request->get_param('id')), 200);
    }

    /**
     * Build the keyword payload to forward to EBQ. Supports both create
     * (full body) and update (only the mutable subset). JSON body for both;
     * we pull through wp_unslash + type-narrow each field so the EBQ
     * validator gets a clean shape regardless of how the JSON came in.
     */
    private function keyword_payload_from_request(WP_REST_Request $request, bool $for_update = false): array
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            $body = $request->get_params();
        }
        $out = [];

        $passthrough_strings = $for_update
            ? ['notes', 'target_url']
            : ['keyword', 'target_domain', 'target_url', 'search_engine', 'search_type', 'country', 'language', 'location', 'tbs', 'notes', 'device'];
        foreach ($passthrough_strings as $key) {
            if (array_key_exists($key, $body) && is_string($body[$key])) {
                $out[$key] = wp_strip_all_tags((string) wp_unslash($body[$key]));
            }
        }

        $passthrough_ints = ['depth', 'check_interval_hours'];
        foreach ($passthrough_ints as $key) {
            if (array_key_exists($key, $body) && (is_int($body[$key]) || is_numeric($body[$key]))) {
                $out[$key] = (int) $body[$key];
            }
        }

        $passthrough_bools = $for_update
            ? ['is_active']
            : ['autocorrect', 'safe_search'];
        foreach ($passthrough_bools as $key) {
            if (array_key_exists($key, $body)) {
                $out[$key] = (bool) $body[$key];
            }
        }

        foreach (['competitors', 'tags'] as $list_key) {
            if (array_key_exists($list_key, $body) && is_array($body[$list_key])) {
                $clean = [];
                foreach ($body[$list_key] as $item) {
                    if (! is_string($item)) continue;
                    $clean_item = trim(wp_strip_all_tags((string) wp_unslash($item)));
                    if ($clean_item !== '') {
                        $clean[] = $clean_item;
                    }
                }
                $out[$list_key] = $clean;
            }
        }

        return $out;
    }

    public function hq_gsc_keywords(WP_REST_Request $request): WP_REST_Response
    {
        $args = array_filter([
            'range' => (string) ($request->get_param('range') ?: '30d'),
            'sort' => (string) $request->get_param('sort'),
            'dir' => (string) $request->get_param('dir'),
            'page' => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('per_page') ?: 25),
            'search' => (string) ($request->get_param('search') ?: ''),
        ], static fn ($v) => $v !== '' && $v !== 0);
        return $this->hq_response(EBQ_Plugin::api_client()->hq_gsc_keywords($args), 200);
    }

    public function hq_pages(WP_REST_Request $request): WP_REST_Response
    {
        $args = array_filter([
            'range' => (string) ($request->get_param('range') ?: '30d'),
            'sort' => (string) $request->get_param('sort'),
            'dir' => (string) $request->get_param('dir'),
            'page' => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('per_page') ?: 25),
            'search' => (string) ($request->get_param('search') ?: ''),
        ], static fn ($v) => $v !== '' && $v !== 0);
        return $this->hq_response(EBQ_Plugin::api_client()->hq_pages($args), 200);
    }

    public function hq_index_status(WP_REST_Request $request): WP_REST_Response
    {
        $args = array_filter([
            'status' => (string) ($request->get_param('status') ?: ''),
            'page' => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('per_page') ?: 25),
            'search' => (string) ($request->get_param('search') ?: ''),
        ], static fn ($v) => $v !== '' && $v !== 0);
        return $this->hq_response(EBQ_Plugin::api_client()->hq_index_status($args), 200);
    }

    public function hq_insights(WP_REST_Request $request): WP_REST_Response
    {
        $type = (string) $request->get_param('type');
        $limit = max(5, min(100, (int) ($request->get_param('limit') ?: 25)));
        return $this->hq_response(EBQ_Plugin::api_client()->hq_insights($type, $limit), 200);
    }

    public function hq_iframe_url(WP_REST_Request $request): WP_REST_Response
    {
        $insight = (string) ($request->get_param('insight') ?: 'cannibalization');
        return new WP_REST_Response(EBQ_Plugin::api_client()->get_iframe_url($insight), 200);
    }

    /**
     * Migration job status. Returns the live transient state for `source`
     * (yoast | rankmath) so the settings-card progress bar can update
     * without a full page reload.
     */
    public function migration_status(WP_REST_Request $request): WP_REST_Response
    {
        $source = sanitize_key((string) ($request->get_param('source') ?? ''));
        if ($source === '') {
            return $this->hq_response(['ok' => false, 'error' => 'missing_source'], 400);
        }
        return $this->hq_response(EBQ_Migration::get_state($source), 200);
    }

    /**
     * Promote a keyword (typically the post's focus keyphrase) to the Rank
     * Tracker. Editor-accessible — no admin cap required, since the user is
     * already trusted to edit the post and choose the keyword. Defaults
     * mirror the modal so a one-click track does the right thing.
     */
    public function track_keyword(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            $body = $request->get_params();
        }
        $keyword = trim((string) wp_unslash((string) ($body['keyword'] ?? '')));
        if ($keyword === '') {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_keyword'], 400);
        }

        $payload = ['keyword' => $keyword];
        foreach (['country', 'language', 'device', 'target_url', 'target_domain'] as $opt) {
            if (! empty($body[$opt]) && is_string($body[$opt])) {
                $payload[$opt] = wp_strip_all_tags((string) wp_unslash($body[$opt]));
            }
        }
        if (! empty($body['tags']) && is_array($body['tags'])) {
            $payload['tags'] = array_values(array_filter(array_map(
                static fn ($t) => is_string($t) ? trim(wp_strip_all_tags((string) wp_unslash($t))) : '',
                $body['tags']
            )));
        }

        $result = EBQ_Plugin::api_client()->hq_create_keyword($payload);
        return new WP_REST_Response($result, 200);
    }

    public function bulk_post_insights(WP_REST_Request $request): WP_REST_Response
    {
        $ids = (array) $request->get_param('post_ids');
        $urls = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $permalink = get_permalink($id);
            if ($permalink) {
                $urls[$id] = $permalink;
            }
        }

        if (empty($urls)) {
            return new WP_REST_Response(['ok' => true, 'rows' => []], 200);
        }

        $response = EBQ_Plugin::api_client()->get_posts_bulk(array_values($urls));
        $results = is_array($response['results'] ?? null) ? $response['results'] : [];

        $rows = [];
        foreach ($urls as $post_id => $url) {
            $rows[(string) $post_id] = isset($results[$url]) ? $results[$url] : null;
        }

        return new WP_REST_Response(['ok' => true, 'rows' => $rows], 200);
    }
}
