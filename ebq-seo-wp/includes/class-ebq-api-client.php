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

        $cache_key = 'ebq_api_' . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'User-Agent' => 'EBQ-SEO-WP/' . EBQ_SEO_VERSION . '; ' . home_url(),
            ],
        ]);

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

        // Successful payload: short cache to keep the editor snappy. We do
        // NOT cache responses where ok===false (e.g. url_not_for_website),
        // so a freshly-correct URL or token recovers immediately.
        if (! (isset($decoded['ok']) && $decoded['ok'] === false)) {
            set_transient($cache_key, $decoded, 5 * MINUTE_IN_SECONDS);
        }

        return $decoded;
    }

    /**
     * Drop every transient this client has set. Called from the settings page
     * "Refresh data" button so a misconfigured first load can be recovered
     * without waiting 5 minutes.
     */
    public static function clear_response_cache(): int
    {
        global $wpdb;

        $like = $wpdb->esc_like('_transient_ebq_api_').'%';
        $rows = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );

        $count = 0;
        foreach ($rows as $row) {
            $key = preg_replace('/^_transient_/', '', (string) $row);
            if ($key !== '' && delete_transient($key)) {
                $count++;
            }
        }

        return $count;
    }
}
