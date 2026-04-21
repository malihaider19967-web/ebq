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
    }

    public function can_edit(): bool
    {
        return current_user_can('edit_posts');
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
}
