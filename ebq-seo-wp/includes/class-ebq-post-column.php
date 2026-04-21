<?php
/**
 * Adds an "EBQ" column to the posts list showing 30d clicks + avg position
 * + cannibalization/tracked badges. Single bulk call per admin screen.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Post_Column
{
    /** @var array<string, array<string, mixed>> */
    private array $bulk_cache = [];

    public function register(): void
    {
        foreach (['post', 'page'] as $type) {
            add_filter("manage_{$type}_posts_columns", [$this, 'add_column']);
            add_action("manage_{$type}_posts_custom_column", [$this, 'render_column'], 10, 2);
        }
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

        $this->prime_bulk_cache();
        $url = get_permalink($post_id);
        if (! $url) {
            echo '<span style="color:#888;">—</span>';

            return;
        }

        $row = $this->bulk_cache[$url] ?? null;
        if (! $row) {
            echo '<span style="color:#888;">—</span>';

            return;
        }

        $clicks = (int) ($row['clicks_30d'] ?? 0);
        $position = $row['avg_position'] ?? null;
        $flags = is_array($row['flags'] ?? null) ? $row['flags'] : [];
        ?>
        <div style="display:flex;flex-direction:column;gap:2px;font-size:11px;">
            <span><strong style="color:#1d4ed8;"><?php echo esc_html(number_format_i18n($clicks)); ?></strong> <span style="color:#64748b;">clicks 30d</span></span>
            <?php if ($position !== null): ?>
                <span style="color:#64748b;">Avg pos <strong><?php echo esc_html((string) $position); ?></strong></span>
            <?php endif; ?>
            <span style="display:inline-flex;gap:4px;margin-top:2px;">
                <?php if (! empty($flags['cannibalized'])): ?>
                    <span style="background:#fef3c7;color:#92400e;border-radius:4px;padding:1px 4px;font-size:9px;font-weight:600;text-transform:uppercase;">cannibalized</span>
                <?php endif; ?>
                <?php if (! empty($flags['tracked'])): ?>
                    <span style="background:#e0e7ff;color:#3730a3;border-radius:4px;padding:1px 4px;font-size:9px;font-weight:600;text-transform:uppercase;">tracked</span>
                <?php endif; ?>
            </span>
        </div>
        <?php
    }

    private function prime_bulk_cache(): void
    {
        if (! empty($this->bulk_cache)) {
            return;
        }

        global $wp_query;
        if (! ($wp_query instanceof WP_Query) || empty($wp_query->posts)) {
            return;
        }

        $urls = [];
        foreach ($wp_query->posts as $post) {
            $permalink = get_permalink($post);
            if ($permalink) {
                $urls[] = $permalink;
            }
        }

        if (empty($urls)) {
            return;
        }

        $response = EBQ_Plugin::api_client()->get_posts_bulk($urls);
        if (! empty($response['results']) && is_array($response['results'])) {
            $this->bulk_cache = $response['results'];
        }
    }
}
