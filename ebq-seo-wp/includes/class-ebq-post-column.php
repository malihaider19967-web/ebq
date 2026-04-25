<?php
/**
 * Post-list "EBQ" column. Renders a skeleton on every row, then a single bulk
 * fetch (post-column-hydrate.js) hits /ebq/v1/bulk-post-insights and replaces
 * the skeleton with rank pill + flags + 30-day clicks/impressions.
 *
 * Keeps /wp-admin/edit.php snappy even with 100+ rows — only one network call.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Post_Column
{
    public function register(): void
    {
        $types = (array) apply_filters('ebq_post_column_post_types', ['post', 'page']);
        foreach ($types as $type) {
            add_filter("manage_{$type}_posts_columns", [$this, 'add_column']);
            add_action("manage_{$type}_posts_custom_column", [$this, 'render_column'], 10, 2);
        }
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }
        if (! EBQ_Plugin::is_configured()) {
            return;
        }

        // Shared admin styles (rank pill, flags, skeleton shimmer).
        $css = EBQ_SEO_PATH.'build/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style('ebq-seo-admin', EBQ_SEO_URL.'build/admin.css', [], (string) filemtime($css));
        }

        $script = EBQ_SEO_PATH.'build/post-column-hydrate.js';
        if (! file_exists($script)) {
            return;
        }
        $asset_file = EBQ_SEO_PATH.'build/post-column-hydrate.asset.php';
        $deps = ['wp-api-fetch', 'wp-dom-ready', 'wp-i18n'];
        $version = (string) filemtime($script);
        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            $deps = $asset['dependencies'] ?? $deps;
            $version = $asset['version'] ?? $version;
        }
        wp_enqueue_script('ebq-post-column-hydrate', EBQ_SEO_URL.'build/post-column-hydrate.js', $deps, $version, true);
        wp_set_script_translations('ebq-post-column-hydrate', 'ebq-seo');
    }

    public function add_column(array $columns): array
    {
        $columns['ebq'] = __('EBQ', 'ebq-seo');

        return $columns;
    }

    public function render_column(string $column, int $post_id): void
    {
        if ($column !== 'ebq') {
            return;
        }
        if (! EBQ_Plugin::is_configured()) {
            echo '<span class="ebq-col-meta">—</span>';

            return;
        }
        printf(
            '<div class="ebq-col-cell" data-ebq-col data-post="%d">'.
                '<span class="ebq-col-skeleton">'.
                    '<span class="ebq-col-shimmer" style="width:60%%;"></span>'.
                    '<span class="ebq-col-shimmer" style="width:40%%;margin-top:4px;"></span>'.
                '</span>'.
            '</div>',
            (int) $post_id
        );
    }
}
