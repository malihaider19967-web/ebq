<?php
/**
 * Lightweight 404 capture for AI-suggested redirects.
 *
 * Hooks `template_redirect` (cheap; runs on every front-end request anyway),
 * checks `is_404()`, dedupes the requested path into a rolling option with
 * hit counts. A WP cron event runs hourly to ship the buffer up to EBQ's
 * /api/v1/posts/report-404s endpoint, where an LLM matches each broken
 * path against the site's existing post inventory and stores a suggestion
 * for the user to review.
 *
 * Filters out:
 *   - Bot user agents (>90% of 404 traffic; not worth LLM tokens)
 *   - Admin / REST / cron / login URLs (not "real" 404s)
 *   - Paths with query strings (cache pollution)
 *   - Already-handled paths (an EBQ_Redirects rule exists)
 *   - Very long paths (likely WAF probes / vulnerability scans)
 *
 * Buffer cap: 200 unique paths. When the cap is reached we drop new
 * entries until the cron drains the buffer — protects against a runaway
 * 404 explosion writing to wp_options every request.
 *
 * Designed so disabling EBQ entirely (no token, plugin deactivated, etc.)
 * still leaves the front-end fast: all writes are option_set on a hot
 * autoloaded option, no external HTTP at request time.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_404_Tracker
{
    private const OPTION_KEY = 'ebq_404_buffer';
    private const CRON_HOOK = 'ebq_send_404_batch';
    private const MAX_BUFFER = 200;
    private const MAX_PATH_LEN = 500;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'capture_404'], 99);
        add_action(self::CRON_HOOK, [$this, 'send_batch']);
        add_action('init', [$this, 'maybe_schedule']);
    }

    /**
     * Schedule the hourly drain job on first request after activation.
     * Idempotent — wp_next_scheduled() returns false when not scheduled.
     */
    public function maybe_schedule(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'hourly', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
        }
    }

    /**
     * Front-end hook: bump the hit counter for the requested path if this
     * is a real 404. Skips admin/REST/cron/login and bot traffic.
     */
    public function capture_404(): void
    {
        if (! is_404()) {
            return;
        }
        if (! EBQ_Plugin::is_configured()) {
            return; // no point tracking — we have no EBQ to ship to.
        }
        if (defined('DOING_AJAX') || defined('DOING_CRON') || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($request_uri === '') {
            return;
        }

        // Strip query string + fragment; we want path-level dedup.
        $path = (string) (parse_url($request_uri, PHP_URL_PATH) ?: '');
        $path = strtolower(rtrim($path, '/'));
        if ($path === '') {
            $path = '/';
        }
        if (strlen($path) > self::MAX_PATH_LEN) {
            return; // likely scanner / WAF probe noise
        }

        // Skip noisy infrastructure paths.
        $skipPrefixes = ['/wp-admin', '/wp-login', '/wp-json', '/wp-cron', '/xmlrpc', '/feed'];
        foreach ($skipPrefixes as $p) {
            if ($path === $p || str_starts_with($path, $p . '/')) {
                return;
            }
        }

        // Skip bot user agents — saves LLM tokens on Googlebot probing
        // for old paths that genuinely don't exist anymore.
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($this->is_bot($ua)) {
            return;
        }

        // Skip if a redirect rule already handles this path — the front-
        // end handler runs before us in normal flow, but on cache misses
        // we sometimes still hit here. No point tracking handled paths.
        if (class_exists('EBQ_Redirects')) {
            $redirects = new EBQ_Redirects();
            if ($redirects->find_by_source($path) !== null) {
                return;
            }
        }

        $this->record_hit($path);
    }

    private function record_hit(string $path): void
    {
        $buffer = get_option(self::OPTION_KEY, []);
        if (! is_array($buffer)) {
            $buffer = [];
        }
        if (isset($buffer[$path])) {
            $buffer[$path]['hits']++;
            $buffer[$path]['last_at'] = time();
        } elseif (count($buffer) < self::MAX_BUFFER) {
            $buffer[$path] = [
                'hits' => 1,
                'first_at' => time(),
                'last_at' => time(),
            ];
        } else {
            return; // buffer full — drain via cron, then resume
        }
        update_option(self::OPTION_KEY, $buffer, false);
    }

    /**
     * Cron hook: ship the buffer to EBQ and clear it. Runs hourly.
     */
    public function send_batch(): void
    {
        if (! EBQ_Plugin::is_configured()) {
            return;
        }
        $buffer = get_option(self::OPTION_KEY, []);
        if (! is_array($buffer) || $buffer === []) {
            return;
        }

        $paths = [];
        foreach ($buffer as $path => $data) {
            if (! is_array($data)) {
                continue;
            }
            $paths[] = [
                'path' => (string) $path,
                'hits' => max(1, (int) ($data['hits'] ?? 1)),
            ];
            if (count($paths) >= 200) {
                break; // EBQ endpoint enforces 200 max anyway
            }
        }

        if ($paths === []) {
            return;
        }

        $response = EBQ_Plugin::api_client()->report_404s($paths);
        // Only clear the buffer on a confirmed-OK response — if the API
        // is down we keep accumulating hits for the next cron run.
        if (is_array($response) && ($response['ok'] ?? false) === true) {
            delete_option(self::OPTION_KEY);
        }
    }

    /**
     * Cheap UA bot match. Doesn't need to be exhaustive — false negatives
     * just mean we ship a few extra paths to EBQ where the gate dedupes them.
     */
    private function is_bot(string $ua): bool
    {
        if ($ua === '') return true;
        $needles = ['bot', 'spider', 'crawler', 'slurp', 'curl/', 'wget/', 'python-requests', 'go-http', 'httpie', 'java/', 'feedfetcher', 'preview'];
        $lower = strtolower($ua);
        foreach ($needles as $n) {
            if (str_contains($lower, $n)) {
                return true;
            }
        }
        return false;
    }
}
