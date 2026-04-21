<?php
/**
 * WP dashboard widget — skeleton on render, hydrated via /wp-json/ebq/v1/dashboard-html.
 * Keeps /wp-admin/ fast on admin login.
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
        wp_enqueue_script(
            'ebq-dashboard-hydrate',
            EBQ_SEO_URL.'build/dashboard-hydrate.js',
            ['wp-api-fetch'],
            EBQ_SEO_VERSION,
            true
        );
    }

    public function render(): void
    {
        if (! EBQ_Plugin::is_configured()) {
            echo '<p>'.esc_html__('Configure the plugin in Settings → EBQ SEO to see insights here.', 'ebq-seo').'</p>';

            return;
        }

        $this->styles();
        ?>
        <div data-ebq-dashboard role="status" aria-live="polite">
            <div class="ebq-widget-grid ebq-widget-skeleton">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="ebq-widget-card-skeleton"><div class="ebq-shimmer" style="height:10px;width:60%;"></div><div class="ebq-shimmer" style="height:22px;width:40%;margin-top:8px;"></div></div>
                <?php endfor; ?>
            </div>
            <p class="ebq-widget-fallback" style="display:none;color:#b91c1c;font-size:11px;"></p>
        </div>
        <?php
    }

    /**
     * Shared content template — reused by the REST proxy after hydrate.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render_content(array $data): string
    {
        $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
        $domain = isset($data['domain']) ? (string) $data['domain'] : '';
        $base = rtrim((string) get_option('ebq_api_base_override', ''), '/');
        if ($base === '') {
            $base = defined('EBQ_API_BASE') ? rtrim((string) EBQ_API_BASE, '/') : 'https://ebq.io';
        }

        $cards = [
            ['cannibalizations', __('Cannibalizations', 'ebq-seo'), 'cannibalization', '#b45309'],
            ['striking_distance', __('Striking distance', 'ebq-seo'), 'striking_distance', '#4f46e5'],
            ['indexing_fails_with_traffic', __('Index fails + traffic', 'ebq-seo'), 'indexing_fails', '#dc2626'],
            ['content_decay', __('Content decay', 'ebq-seo'), 'content_decay', '#334155'],
        ];

        ob_start();
        if ($domain !== '') {
            echo '<p style="font-size:11px;color:#64748b;margin:0 0 6px;">'.esc_html(sprintf(__('Website: %s', 'ebq-seo'), $domain)).'</p>';
        }
        echo '<div class="ebq-widget-grid">';
        foreach ($cards as [$key, $label, $insight, $color]) {
            $value = (int) ($counts[$key] ?? 0);
            $href = $base !== '' ? $base.'/reports?insight='.$insight : '#';
            printf(
                '<a class="ebq-widget-card" href="%s" target="_blank" rel="noopener"><div class="ebq-widget-label">%s</div><div class="ebq-widget-value" style="color:%s;">%s</div></a>',
                esc_url($href),
                esc_html($label),
                esc_attr($color),
                esc_html(number_format_i18n($value))
            );
        }
        echo '</div>';

        return (string) ob_get_clean();
    }

    private function styles(): void
    {
        static $emitted = false;
        if ($emitted) {
            return;
        }
        $emitted = true;
        ?>
        <style>
            .ebq-widget-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:6px;}
            .ebq-widget-card{display:block;border:1px solid #e2e8f0;border-radius:8px;padding:12px;background:#fff;text-decoration:none;color:inherit;transition:border-color .15s;}
            .ebq-widget-card:hover{border-color:#6366f1;}
            .ebq-widget-label{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;}
            .ebq-widget-value{margin-top:6px;font-size:22px;font-weight:700;font-variant-numeric:tabular-nums;}
            .ebq-widget-card-skeleton{border:1px solid #e2e8f0;border-radius:8px;padding:12px;background:#fff;}
            .ebq-shimmer{background:linear-gradient(90deg,#f1f5f9 0%,#e2e8f0 50%,#f1f5f9 100%);background-size:200% 100%;animation:ebq-shimmer 1.2s infinite;border-radius:3px;}
            @keyframes ebq-shimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
        </style>
        <?php
    }
}
