<?php
/**
 * Enqueues the v2 SEO editor bundle (build/seo-panel.js).
 *
 * Renders as a Gutenberg PluginDocumentSettingPanel — no build step required.
 * Classic editor / Elementor users get the equivalent fields via
 * EBQ_Seo_Fields_Meta_Box (class-ebq-seo-fields-meta-box.php).
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Seo_Panel
{
    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        $bundle = EBQ_SEO_PATH.'build/seo-panel.js';
        if (! file_exists($bundle)) {
            return;
        }

        $dependencies = [
            'wp-plugins',
            'wp-edit-post',
            'wp-editor',
            'wp-element',
            'wp-components',
            'wp-data',
            'wp-api-fetch',
            'wp-i18n',
        ];

        wp_enqueue_script(
            'ebq-seo-editor',
            EBQ_SEO_URL.'build/seo-panel.js',
            $dependencies,
            EBQ_SEO_VERSION,
            true
        );

        wp_set_script_translations('ebq-seo-editor', 'ebq-seo');
    }
}
