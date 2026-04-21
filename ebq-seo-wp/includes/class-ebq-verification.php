<?php
/**
 * Serves the EBQ verification code at /.well-known/ebq-verification.txt via
 * parse_request. No physical file needed — the plugin intercepts the request.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Verification
{
    public const PATH = '.well-known/ebq-verification.txt';

    public function register(): void
    {
        add_action('parse_request', [$this, 'maybe_serve']);
    }

    public function maybe_serve(WP $wp): void
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($request_uri, PHP_URL_PATH) ?: '';
        if (ltrim($path, '/') !== self::PATH) {
            return;
        }

        $code = trim((string) get_option('ebq_challenge_code', ''));

        status_header(200);
        nocache_headers();
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow');
        echo $code;
        exit;
    }
}
