<?php
/**
 * EBQ Head Quarter — top-level WordPress admin page.
 *
 * Registers a high-visibility menu (position 3, immediately after Dashboard)
 * and renders a single React mount that proxies all data through the existing
 * `/wp-json/ebq/v1/hq/*` endpoints. The MOAT stays on the EBQ.io app — this
 * is purely a presentation layer.
 *
 * Capability: `manage_options`. The proxy routes enforce the same gate so a
 * compromised lower-privilege user can't fish for analytics by hitting the
 * REST endpoints directly.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Hq_Page
{
    public const SLUG = 'ebq-hq';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_bar_menu', [$this, 'register_admin_bar'], 80);
    }

    /**
     * Global "+ Track keyword" shortcut in the WP admin bar so adding a
     * keyword is one click from anywhere in the admin (or front-end if
     * the user is logged in). Lands on the HQ Rank Tracker tab with the
     * AddKeywordModal pre-opened — empty form for free-typing.
     */
    public function register_admin_bar(\WP_Admin_Bar $bar): void
    {
        if (! current_user_can('edit_posts') || ! EBQ_Plugin::is_configured()) {
            return;
        }
        $bar->add_node([
            'id'    => 'ebq-track-keyword',
            'title' => '<span class="ab-icon dashicons dashicons-search" style="top:3px"></span>'
                     . esc_html__('Track keyword', 'ebq-seo'),
            'href'  => add_query_arg(['page' => self::SLUG, 'ebq_track' => '1'], admin_url('admin.php')),
            'meta'  => [
                'title' => __('Add a keyword to EBQ Rank Tracker', 'ebq-seo'),
            ],
        ]);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('EBQ Head Quarter', 'ebq-seo'),
            __('EBQ HQ', 'ebq-seo'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            $this->menu_icon(),
            3
        );
    }

    public function enqueue(string $hook): void
    {
        // The hook for a top-level page is `toplevel_page_<slug>`.
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }

        $bundle = EBQ_SEO_PATH . 'build/hq.js';
        if (! file_exists($bundle)) {
            return;
        }

        $asset_file = EBQ_SEO_PATH . 'build/hq.asset.php';
        $deps = ['wp-element', 'wp-i18n', 'wp-api-fetch'];
        $version = (string) filemtime($bundle);
        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            $deps = $asset['dependencies'] ?? $deps;
            $version = $asset['version'] ?? $version;
        }

        wp_enqueue_script('ebq-hq', EBQ_SEO_URL . 'build/hq.js', $deps, $version, true);
        wp_set_script_translations('ebq-hq', 'ebq-seo');

        $css = EBQ_SEO_PATH . 'build/hq.css';
        if (file_exists($css)) {
            wp_enqueue_style('ebq-hq', EBQ_SEO_URL . 'build/hq.css', [], (string) filemtime($css));
        }

        wp_localize_script('ebq-hq', 'ebqHqConfig', [
            'restUrl'        => esc_url_raw(rest_url('ebq/v1/')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'siteName'       => get_bloginfo('name'),
            'workspaceDomain' => (string) get_option('ebq_website_domain', ''),
            'isConnected'    => EBQ_Plugin::is_configured(),
            'settingsUrl'    => admin_url('admin.php?page=ebq-seo'),
            'connectUrl'     => admin_url('admin.php?page=ebq-seo'),
            'pluginVersion'  => EBQ_SEO_VERSION,
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="ebq-hq-wrap">';
        echo '<div id="ebq-hq-root">';
        echo '<div class="ebq-hq-bootskel"><div class="ebq-hq-bootskel__bar"></div><div class="ebq-hq-bootskel__bar"></div><div class="ebq-hq-bootskel__bar"></div></div>';
        echo '</div>';
        echo '<noscript><p style="font-size:13px;color:#64748b;padding:14px;border:1px solid #e2e8f0;border-radius:8px;background:#fff">';
        esc_html_e('EBQ Head Quarter requires JavaScript. Please enable it in your browser.', 'ebq-seo');
        echo '</p></noscript>';
        echo '</div>';
    }

    /**
     * Inline SVG menu icon — the green-on-dark "E" mark. Inlined as a data URL
     * so WordPress will recolor it to match the active admin theme (default
     * white / hover blue) using its admin-bar SVG masking.
     */
    private function menu_icon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="black" d="M3 3h14v3H6v3h9v3H6v3h11v3H3z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
