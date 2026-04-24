<?php
/**
 * Registers the Gutenberg sidebar asset. The React app (src/sidebar/index.js)
 * is built to build/sidebar.js via @wordpress/scripts.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Gutenberg_Sidebar
{
    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        $bundle = EBQ_SEO_PATH . 'build/sidebar.js';
        if (! file_exists($bundle)) {
            // The React sidebar is an optional build artifact. The rest of the
            // plugin (admin column, dashboard widget, settings, verification)
            // works without it. Run `npm install && npm run build` in the
            // plugin root to enable the Gutenberg panel.
            return;
        }

        $asset_file = EBQ_SEO_PATH . 'build/sidebar.asset.php';
        $dependencies = ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n'];
        $version = EBQ_SEO_VERSION;

        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            $dependencies = $asset['dependencies'] ?? $dependencies;
            $version = $asset['version'] ?? $version;
        }

        wp_enqueue_script(
            'ebq-seo-sidebar',
            EBQ_SEO_URL . 'build/sidebar.js',
            $dependencies,
            $version,
            true
        );

        wp_localize_script('ebq-seo-sidebar', 'ebqSeoPublic', [
            'appBase' => EBQ_Api_Client::base_url(),
            'homeUrl' => home_url('/'),
            'siteName' => get_bloginfo('name'),
            'titleSep' => EBQ_Title_Template::get_sep(),
        ]);

        wp_set_script_translations('ebq-seo-sidebar', 'ebq-seo');
    }
}
