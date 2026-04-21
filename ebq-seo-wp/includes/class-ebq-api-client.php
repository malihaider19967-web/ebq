<?php
/**
 * Thin wrapper around wp_remote_request that adds the plugin's bearer token.
 *
 * Base URL defaults to https://app.ebq.io. Self-hosted installs override by
 * defining the EBQ_API_BASE constant in wp-config.php — no UI field needed.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Api_Client
{
    public const DEFAULT_BASE = 'https://app.ebq.io';

    public function __construct(
        private readonly string $token,
        private readonly string $base_url = '',
    ) {}

    public static function base_url(): string
    {
        if (defined('EBQ_API_BASE') && is_string(EBQ_API_BASE) && EBQ_API_BASE !== '') {
            return rtrim((string) EBQ_API_BASE, '/');
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
            'timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'User-Agent' => 'EBQ-SEO-WP/' . EBQ_SEO_VERSION . '; ' . home_url(),
            ],
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => 'network_error', 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 400 || ! is_array($decoded)) {
            return ['ok' => false, 'error' => 'http_' . $code, 'body' => $body];
        }

        set_transient($cache_key, $decoded, 5 * MINUTE_IN_SECONDS);

        return $decoded;
    }
}
