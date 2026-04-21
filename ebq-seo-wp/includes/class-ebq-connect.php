<?php
/**
 * One-click OAuth-style connect flow.
 *
 *   1. Settings page shows a "Connect to EBQ" link built by build_connect_url().
 *   2. Click redirects to https://app.ebq.io/wordpress/connect with site_url +
 *      redirect + state params. User authenticates in EBQ and picks a website.
 *   3. EBQ bounces back to admin URL with ?ebq_token=...&website_id=...&state=...
 *   4. maybe_catch_callback() validates state on admin_init, persists the token,
 *      and redirects to a clean settings URL.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Connect
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'maybe_catch_callback']);
    }

    /**
     * Build the URL to hand off to EBQ's consent screen. Stores a fresh state
     * nonce in options so we can validate it on return.
     */
    public static function build_connect_url(): string
    {
        $state = wp_generate_password(32, false, false);
        update_option('ebq_connect_state', $state);

        $redirect = admin_url('options-general.php?page=ebq-seo&ebq_cb=1');
        $site_url = home_url('/');

        return add_query_arg(
            [
                'site_url' => $site_url,
                'redirect' => $redirect,
                'state' => $state,
            ],
            EBQ_Api_Client::base_url() . '/wordpress/connect'
        );
    }

    public static function disconnect_url(): string
    {
        return wp_nonce_url(
            admin_url('options-general.php?page=ebq-seo&ebq_disconnect=1'),
            'ebq_disconnect'
        );
    }

    public function maybe_catch_callback(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Disconnect link: clears local credentials only. EBQ-side revoke is a separate step.
        if (isset($_GET['ebq_disconnect']) && check_admin_referer('ebq_disconnect')) {
            update_option('ebq_site_token', '');
            update_option('ebq_website_id', 0);
            update_option('ebq_website_domain', '');
            wp_safe_redirect(admin_url('options-general.php?page=ebq-seo&ebq_status=disconnected'));
            exit;
        }

        if (empty($_GET['ebq_cb']) || empty($_GET['ebq_token']) || empty($_GET['state'])) {
            return;
        }

        $expected_state = (string) get_option('ebq_connect_state', '');
        $received_state = (string) wp_unslash($_GET['state']);
        if ($expected_state === '' || ! hash_equals($expected_state, $received_state)) {
            wp_safe_redirect(admin_url('options-general.php?page=ebq-seo&ebq_status=state_mismatch'));
            exit;
        }

        $token = sanitize_text_field((string) wp_unslash($_GET['ebq_token']));
        $website_id = isset($_GET['website_id']) ? (int) $_GET['website_id'] : 0;
        $domain = isset($_GET['ebq_domain']) ? sanitize_text_field((string) wp_unslash($_GET['ebq_domain'])) : '';

        if ($token === '' || $website_id <= 0) {
            wp_safe_redirect(admin_url('options-general.php?page=ebq-seo&ebq_status=bad_token'));
            exit;
        }

        update_option('ebq_site_token', $token);
        update_option('ebq_website_id', $website_id);
        update_option('ebq_website_domain', $domain);
        delete_option('ebq_connect_state');

        wp_safe_redirect(admin_url('options-general.php?page=ebq-seo&ebq_status=connected'));
        exit;
    }
}
