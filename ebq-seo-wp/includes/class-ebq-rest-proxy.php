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
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
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
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_keywords'],
        ]);
        register_rest_route('ebq/v1', '/hq/keywords/(?P<id>\d+)/history', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_view_hq'],
            'callback' => [$this, 'hq_keyword_history'],
            'args' => ['id' => ['validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v > 0]],
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
    }

    public function can_edit(): bool
    {
        return current_user_can('edit_posts');
    }

    public function can_view_hq(): bool
    {
        return current_user_can('manage_options');
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
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_overview((string) ($request->get_param('range') ?: '30d')), 200);
    }

    public function hq_performance(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_performance((string) ($request->get_param('range') ?: '30d')), 200);
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
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_keywords($args), 200);
    }

    public function hq_keyword_history(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_keyword_history((int) $request->get_param('id')), 200);
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
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_pages($args), 200);
    }

    public function hq_index_status(WP_REST_Request $request): WP_REST_Response
    {
        $args = array_filter([
            'status' => (string) ($request->get_param('status') ?: ''),
            'page' => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('per_page') ?: 25),
            'search' => (string) ($request->get_param('search') ?: ''),
        ], static fn ($v) => $v !== '' && $v !== 0);
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_index_status($args), 200);
    }

    public function hq_insights(WP_REST_Request $request): WP_REST_Response
    {
        $type = (string) $request->get_param('type');
        $limit = max(5, min(100, (int) ($request->get_param('limit') ?: 25)));
        return new WP_REST_Response(EBQ_Plugin::api_client()->hq_insights($type, $limit), 200);
    }

    public function hq_iframe_url(WP_REST_Request $request): WP_REST_Response
    {
        $insight = (string) ($request->get_param('insight') ?: 'cannibalization');
        return new WP_REST_Response(EBQ_Plugin::api_client()->get_iframe_url($insight), 200);
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
