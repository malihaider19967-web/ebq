<?php
/**
 * WP dashboard widget mirroring EBQ's insight-count cards.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Dashboard_Widget
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add_widget']);
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

    public function render(): void
    {
        if (! EBQ_Plugin::is_configured()) {
            echo '<p>' . esc_html__('Configure the plugin in Settings → EBQ SEO to see insights here.', 'ebq-seo') . '</p>';

            return;
        }

        $dashboard = EBQ_Plugin::api_client()->get_dashboard();
        $counts = is_array($dashboard['counts'] ?? null) ? $dashboard['counts'] : [];
        $domain = isset($dashboard['domain']) ? (string) $dashboard['domain'] : '';

        $cards = [
            ['key' => 'cannibalizations', 'label' => __('Cannibalizations', 'ebq-seo'), 'insight' => 'cannibalization', 'color' => '#b45309'],
            ['key' => 'striking_distance', 'label' => __('Striking distance', 'ebq-seo'), 'insight' => 'striking_distance', 'color' => '#4f46e5'],
            ['key' => 'indexing_fails_with_traffic', 'label' => __('Index fails + traffic', 'ebq-seo'), 'insight' => 'indexing_fails', 'color' => '#dc2626'],
            ['key' => 'content_decay', 'label' => __('Content decay', 'ebq-seo'), 'insight' => 'content_decay', 'color' => '#334155'],
        ];

        $base = rtrim((string) get_option('ebq_api_base', ''), '/');

        echo '<style>.ebq-widget-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:6px;}.ebq-widget-card{display:block;border:1px solid #e2e8f0;border-radius:8px;padding:12px;background:#fff;text-decoration:none;color:inherit;transition:border-color .15s;}.ebq-widget-card:hover{border-color:#6366f1;}.ebq-widget-label{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;}.ebq-widget-value{margin-top:6px;font-size:22px;font-weight:700;font-variant-numeric:tabular-nums;}</style>';

        if ($domain !== '') {
            echo '<p style="font-size:11px;color:#64748b;margin:0 0 6px;">' . esc_html(sprintf(__('Website: %s', 'ebq-seo'), $domain)) . '</p>';
        }
        echo '<div class="ebq-widget-grid">';
        foreach ($cards as $card) {
            $value = (int) ($counts[$card['key']] ?? 0);
            $href = $base !== '' ? esc_url($base . '/reports?insight=' . $card['insight']) : '#';
            echo '<a class="ebq-widget-card" href="' . $href . '" target="_blank" rel="noopener">';
            echo '<div class="ebq-widget-label">' . esc_html($card['label']) . '</div>';
            echo '<div class="ebq-widget-value" style="color:' . esc_attr($card['color']) . ';">' . esc_html(number_format_i18n($value)) . '</div>';
            echo '</a>';
        }
        echo '</div>';
    }
}
