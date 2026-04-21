<?php
/**
 * Post-list EBQ column — renders instant "—" placeholders, then a single
 * bulk JS fetch hydrates every row once the page is fully loaded.
 * Keeps /wp-admin/edit.php snappy even with 100+ rows.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Post_Column
{
    public function register(): void
    {
        foreach (['post', 'page'] as $type) {
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
        wp_enqueue_script(
            'ebq-post-column-hydrate',
            EBQ_SEO_URL.'build/post-column-hydrate.js',
            ['wp-api-fetch'],
            EBQ_SEO_VERSION,
            true
        );
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
            echo '<span style="color:#888;">—</span>';

            return;
        }
        printf(
            '<div class="ebq-col-cell" data-ebq-col data-post="%d"><span class="ebq-col-skeleton"><span class="ebq-col-shimmer" style="width:60%%;"></span><span class="ebq-col-shimmer" style="width:40%%;margin-top:3px;"></span></span></div>',
            (int) $post_id
        );
    }
}
