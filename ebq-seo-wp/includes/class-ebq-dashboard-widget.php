<?php
/**
 * WordPress dashboard widget — skeleton on render, hydrated by
 * dashboard-hydrate.js which pulls /ebq/v1/dashboard-html. Keeps /wp-admin/
 * fast at admin login: zero blocking calls during page render.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Dashboard_Widget
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add_widget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_widget(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        wp_add_dashboard_widget(
            'ebq_seo_insights',
            __('EBQ SEO insights', 'ebq-seo'),
            [$this, 'render']
        );
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }
        if (! EBQ_Plugin::is_configured()) {
            return;
        }

        $css = EBQ_SEO_PATH.'build/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style('ebq-seo-admin', EBQ_SEO_URL.'build/admin.css', [], (string) filemtime($css));
        }

        $script = EBQ_SEO_PATH.'build/dashboard-hydrate.js';
        if (! file_exists($script)) {
            return;
        }
        $asset_file = EBQ_SEO_PATH.'build/dashboard-hydrate.asset.php';
        $deps = ['wp-api-fetch', 'wp-dom-ready', 'wp-i18n'];
        $version = (string) filemtime($script);
        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            $deps = $asset['dependencies'] ?? $deps;
            $version = $asset['version'] ?? $version;
        }
        wp_enqueue_script('ebq-dashboard-hydrate', EBQ_SEO_URL.'build/dashboard-hydrate.js', $deps, $version, true);
        wp_set_script_translations('ebq-dashboard-hydrate', 'ebq-seo');
    }

    public function render(): void
    {
        if (! EBQ_Plugin::is_configured()) {
            printf(
                '<p style="font-size:12px;color:#64748b;">%s <a href="%s">%s</a>.</p>',
                esc_html__('Connect this site to EBQ to see insights.', 'ebq-seo'),
                esc_url(admin_url('options-general.php?page=ebq-seo')),
                esc_html__('Open settings', 'ebq-seo')
            );

            return;
        }
        ?>
        <div data-ebq-dashboard role="status" aria-live="polite">
            <div class="ebq-widget-grid ebq-widget-skeleton">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="ebq-widget-card-skeleton">
                        <span class="ebq-shimmer" style="height:10px;width:60%;"></span>
                        <span class="ebq-shimmer" style="height:24px;width:50%;margin-top:8px;"></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Server-rendered card grid — also reused by the REST proxy so we don't
     * duplicate markup in JS.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render_content(array $data): string
    {
        $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
        $domain = isset($data['domain']) ? (string) $data['domain'] : '';

        $base = (string) get_option('ebq_api_base_override', '');
        if ($base === '') {
            $base = defined('EBQ_API_BASE') ? (string) EBQ_API_BASE : 'https://ebq.io';
        }
        $base = rtrim($base, '/');

        $cards = [
            ['cannibalizations',           __('Cannibalizations', 'ebq-seo'),       'cannibalization', 'bad',  __('Pages competing for the same query', 'ebq-seo')],
            ['striking_distance',          __('Striking distance', 'ebq-seo'),      'striking_distance', 'warn', __('Queries on positions 5–20', 'ebq-seo')],
            ['indexing_fails_with_traffic',__('Index fails + traffic', 'ebq-seo'),  'indexing_fails',  'bad',  __('Indexed: false, but still visible', 'ebq-seo')],
            ['content_decay',              __('Content decay', 'ebq-seo'),          'content_decay',   'warn', __('Pages losing organic clicks', 'ebq-seo')],
        ];

        ob_start();
        if ($domain !== '') {
            ?>
            <div class="ebq-widget-meta">
                <span class="ebq-widget-meta__pill"><?php esc_html_e('EBQ', 'ebq-seo'); ?></span>
                <?php echo esc_html($domain); ?>
            </div>
            <?php
        }
        echo '<div class="ebq-widget-grid">';
        foreach ($cards as [$key, $label, $insight, $tone, $hint]) {
            $value = (int) ($counts[$key] ?? 0);
            $href = $base !== '' ? $base.'/reports?insight='.$insight : '#';
            $value_class = $value > 0 ? 'ebq-widget-card__value--'.$tone : '';
            ?>
            <a class="ebq-widget-card" href="<?php echo esc_url($href); ?>" target="_blank" rel="noopener">
                <p class="ebq-widget-card__label"><?php echo esc_html($label); ?></p>
                <p class="ebq-widget-card__value <?php echo esc_attr($value_class); ?>"><?php echo esc_html(number_format_i18n($value)); ?></p>
                <p class="ebq-widget-card__hint"><?php echo esc_html($hint); ?></p>
            </a>
            <?php
        }
        echo '</div>';
        if ($base !== '') {
            ?>
            <a class="ebq-widget-cta" href="<?php echo esc_url($base.'/reports'); ?>" target="_blank" rel="noopener">
                <?php esc_html_e('Open full EBQ reports →', 'ebq-seo'); ?>
            </a>
            <?php
        }

        return (string) ob_get_clean();
    }
}
