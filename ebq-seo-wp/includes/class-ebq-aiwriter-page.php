<?php
/**
 * EBQ AI Writer — top-level WP-admin page that hosts the standalone
 * draft builder. Reuses the HQ React bundle (`build/hq.js`) but mounts
 * a different React root (`#ebq-aiwriter-root`) so the AI Writer
 * doesn't ship inside the HQ tabs anymore. Lives at its own admin URL
 * (`admin.php?page=ebq-ai-writer`) and gets its own menu item next to
 * EBQ HQ.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_AiWriter_Page
{
    public const SLUG = 'ebq-ai-writer';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu'], 4);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('EBQ AI Writer', 'ebq-seo'),
            __('AI Writer', 'ebq-seo'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            $this->menu_icon(),
            4 // sits just below EBQ HQ (which has position 3)
        );
    }

    public function enqueue(string $hook): void
    {
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

        // Native WP rich-text editor — TinyMCE, plugins, media uploader.
        wp_enqueue_editor();
        wp_enqueue_media();

        $css = EBQ_SEO_PATH . 'build/hq.css';
        if (file_exists($css)) {
            wp_enqueue_style('ebq-hq', EBQ_SEO_URL . 'build/hq.css', [], (string) filemtime($css));
        }

        wp_localize_script('ebq-hq', 'ebqHqConfig', [
            'restUrl'         => esc_url_raw(rest_url('ebq/v1/')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'siteName'        => get_bloginfo('name'),
            'workspaceDomain' => (string) get_option('ebq_website_domain', ''),
            'isConnected'     => EBQ_Plugin::is_configured(),
            'settingsUrl'     => admin_url('admin.php?page=ebq-seo'),
            'connectUrl'      => admin_url('admin.php?page=ebq-seo'),
            'pluginVersion'   => EBQ_SEO_VERSION,
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="ebq-hq-wrap">';
        echo '<div id="ebq-aiwriter-root">';
        echo '<div class="ebq-hq-bootskel"><div class="ebq-hq-bootskel__bar"></div><div class="ebq-hq-bootskel__bar"></div><div class="ebq-hq-bootskel__bar"></div></div>';
        echo '</div>';
        echo '<noscript><p style="font-size:13px;color:#64748b;padding:14px;border:1px solid #e2e8f0;border-radius:8px;background:#fff">';
        esc_html_e('EBQ AI Writer requires JavaScript. Please enable it in your browser.', 'ebq-seo');
        echo '</p></noscript>';
        echo '</div>';
    }

    /**
     * Inline SVG menu icon — sparkle/pencil mark, recolored by WP's admin
     * theme via the standard SVG masking treatment.
     */
    private function menu_icon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="black" d="M14.7 2.3a1 1 0 0 1 1.4 0l1.6 1.6a1 1 0 0 1 0 1.4l-9.6 9.6-3.5.7.7-3.5 9.4-9.8zM3 17h14v2H3z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
