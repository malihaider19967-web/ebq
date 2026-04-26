<?php
/**
 * One-click OAuth-style connect flow.
 *
 *   1. Settings page "Connect to EBQ" button links to /wp-admin/admin-post.php
 *      ?action=ebq_start_connect&_wpnonce=...
 *   2. start_connect() mints a fresh state, stores it, and 302s to
 *      https://ebq.io/wordpress/connect with site_url + redirect + state.
 *   3. User authenticates in EBQ and picks a website.
 *   4. EBQ bounces back to /wp-admin/admin.php?page=ebq-seo
 *      &ebq_token=...&website_id=...&state=...&ebq_domain=...
 *   5. maybe_catch_callback() validates state on admin_init, persists the
 *      token, and redirects to a clean settings URL.
 *
 * State is only generated at click time (not on every settings page render),
 * so reloading the settings page before the callback returns cannot invalidate
 * an in-flight connection.
 *
 * Callback presence is detected by `ebq_token` alone, not by a flag parameter
 * like `ebq_cb=1` — some WAFs/CDNs strip unknown bare flags from admin URLs.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Connect
{
    public const NONCE_ACTION = 'ebq_start_connect';

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybe_catch_callback']);
        add_action('admin_post_ebq_start_connect', [$this, 'start_connect']);
    }

    /**
     * Same logic as maybe_catch_callback() but WITHOUT the redirect + exit —
     * intended to be called from the settings render as a fallback when
     * admin_init did not process the callback for some reason (caching plugin,
     * security filter, etc.). Returns the result so the view can show it.
     *
     * @return array{outcome: string, message: string, debug: array<string, mixed>}|null
     */
    public static function process_callback_inline(): ?array
    {
        if (! current_user_can('manage_options')) {
            return null;
        }
        if (empty($_GET['ebq_token']) || empty($_GET['state'])) {
            return null;
        }

        $token = sanitize_text_field((string) wp_unslash($_GET['ebq_token']));
        $received_state = (string) wp_unslash($_GET['state']);
        $website_id = isset($_GET['website_id']) ? (int) $_GET['website_id'] : 0;
        $domain = isset($_GET['ebq_domain']) ? sanitize_text_field((string) wp_unslash($_GET['ebq_domain'])) : '';
        // Tier carried on connect so the editor can render Pro vs. Free UI
        // immediately on first load, before the first score request returns
        // the canonical current tier. Always reconciled to canonical via
        // every API response that includes `tier`.
        $tier = isset($_GET['ebq_tier']) ? sanitize_text_field((string) wp_unslash($_GET['ebq_tier'])) : 'free';
        if (! in_array($tier, ['free', 'pro'], true)) {
            $tier = 'free';
        }
        $expected_state = (string) get_option('ebq_connect_state', '');

        $debug = [
            'state_expected_len' => strlen($expected_state),
            'state_got_len' => strlen($received_state),
            'state_match' => ($expected_state !== '' && $received_state !== '' && hash_equals($expected_state, $received_state)),
            'token_len' => strlen($token),
            'website_id' => $website_id,
            'domain' => $domain,
        ];

        if ($expected_state === '' || $received_state === '' || ! hash_equals($expected_state, $received_state)) {
            return ['outcome' => 'state_mismatch', 'message' => __('State did not match. Click Connect to EBQ again.', 'ebq-seo'), 'debug' => $debug];
        }

        if ($token === '' || $website_id <= 0) {
            return ['outcome' => 'bad_token', 'message' => __('EBQ did not send a valid token.', 'ebq-seo'), 'debug' => $debug];
        }

        update_option('ebq_site_token', $token);
        update_option('ebq_website_id', $website_id);
        update_option('ebq_website_domain', $domain);
        update_option('ebq_site_tier', $tier);
        delete_option('ebq_connect_state');
        delete_option('ebq_last_connect_error');

        return ['outcome' => 'connected', 'message' => __('Connected successfully.', 'ebq-seo'), 'debug' => $debug];
    }

    /**
     * Link the settings page puts behind the "Connect to EBQ" button.
     */
    public static function start_url(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=ebq_start_connect'),
            self::NONCE_ACTION
        );
    }

    public static function disconnect_url(): string
    {
        return wp_nonce_url(
            admin_url('admin.php?page=ebq-seo&ebq_disconnect=1'),
            'ebq_disconnect'
        );
    }

    /**
     * admin-post.php handler: mint fresh state, redirect to EBQ's consent URL.
     */
    public function start_connect(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to connect this site to EBQ.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE_ACTION);

        $state = wp_generate_password(32, false, false);
        update_option('ebq_connect_state', $state);

        $redirect = admin_url('admin.php?page=ebq-seo');
        $site_url = home_url('/');

        $target = add_query_arg(
            [
                'site_url' => $site_url,
                'redirect' => $redirect,
                'state' => $state,
            ],
            EBQ_Api_Client::base_url() . '/wordpress/connect'
        );

        // Not wp_safe_redirect — target is an external (EBQ) URL by design.
        wp_redirect($target);
        exit;
    }

    public function maybe_catch_callback(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Disconnect link: clears local credentials only.
        if (isset($_GET['ebq_disconnect']) && check_admin_referer('ebq_disconnect')) {
            update_option('ebq_site_token', '');
            update_option('ebq_website_id', 0);
            update_option('ebq_website_domain', '');
            wp_safe_redirect(admin_url('admin.php?page=ebq-seo&ebq_status=disconnected'));
            exit;
        }

        // Callback detection: rely on ebq_token presence, not on a flag param
        // (bare flags like ebq_cb=1 are commonly stripped by WAF/CDN rules).
        if (empty($_GET['ebq_token'])) {
            return;
        }
        // Only engage on our own settings page, so other admin pages aren't affected.
        if (! isset($_GET['page']) || sanitize_key((string) wp_unslash($_GET['page'])) !== 'ebq-seo') {
            return;
        }

        $token = sanitize_text_field((string) wp_unslash($_GET['ebq_token']));
        $received_state = isset($_GET['state']) ? (string) wp_unslash($_GET['state']) : '';
        $website_id = isset($_GET['website_id']) ? (int) $_GET['website_id'] : 0;
        $domain = isset($_GET['ebq_domain']) ? sanitize_text_field((string) wp_unslash($_GET['ebq_domain'])) : '';
        $expected_state = (string) get_option('ebq_connect_state', '');

        $fail = static function (string $status) use ($received_state, $expected_state, $token, $website_id): void {
            $summary = sprintf(
                '[%s] state_expected=%s state_got=%s token_len=%d website_id=%d ts=%s',
                $status,
                $expected_state !== '' ? substr($expected_state, 0, 6).'…' : 'empty',
                $received_state !== '' ? substr($received_state, 0, 6).'…' : 'empty',
                strlen($token),
                $website_id,
                current_time('mysql')
            );
            update_option('ebq_last_connect_error', $summary);
            error_log('EBQ SEO connect failure: '.$summary);
            wp_safe_redirect(admin_url('admin.php?page=ebq-seo&ebq_status='.$status));
            exit;
        };

        if ($expected_state === '' || $received_state === '' || ! hash_equals($expected_state, $received_state)) {
            $fail('state_mismatch');
        }

        if ($token === '' || $website_id <= 0) {
            $fail('bad_token');
        }

        update_option('ebq_site_token', $token);
        update_option('ebq_website_id', $website_id);
        update_option('ebq_website_domain', $domain);
        delete_option('ebq_connect_state');
        delete_option('ebq_last_connect_error');

        wp_safe_redirect(admin_url('admin.php?page=ebq-seo&ebq_status=connected'));
        exit;
    }
}
