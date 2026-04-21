<?php
/**
 * Classic meta box — renders EBQ insights on every post/page edit screen
 * (post.php, post-new.php, and inside both the block editor and the classic
 * editor). Visible regardless of Elementor, Classic Editor plugin, page
 * templates, or block-editor opt-out filters.
 *
 * Kept intentionally simple: PHP-rendered HTML, no React, no build step.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Meta_Box
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
    }

    public function add_meta_box(string $post_type): void
    {
        $supported = apply_filters('ebq_seo_meta_box_post_types', ['post', 'page']);
        if (! in_array($post_type, $supported, true)) {
            return;
        }

        add_meta_box(
            'ebq-seo-insights',
            __('EBQ SEO insights', 'ebq-seo'),
            [$this, 'render'],
            $post_type,
            'side',
            'high'
        );
    }

    public function render(WP_Post $post): void
    {
        if (! EBQ_Plugin::is_configured()) {
            echo '<p style="font-size:12px;color:#64748b;">' .
                esc_html__('Connect this site to EBQ in Settings → EBQ SEO to see insights here.', 'ebq-seo') .
                '</p>';

            return;
        }

        $url = get_permalink($post);
        if (! $url) {
            echo '<p style="font-size:12px;color:#64748b;">' .
                esc_html__('Save this post first so EBQ can look up its insights.', 'ebq-seo') .
                '</p>';

            return;
        }

        $data = EBQ_Plugin::api_client()->get_post_insights((string) $post->ID, $url);

        if (empty($data) || ! is_array($data) || ($data['ok'] ?? null) === false) {
            $error = is_array($data) ? (string) ($data['error'] ?? 'unknown') : 'no_response';
            echo '<p style="font-size:12px;color:#b91c1c;">' .
                esc_html(sprintf(__('Could not load insights (%s).', 'ebq-seo'), $error)) .
                '</p>';

            return;
        }

        $gsc = is_array($data['gsc'] ?? null) ? $data['gsc'] : [];
        $totals30 = is_array($gsc['totals_30d'] ?? null) ? $gsc['totals_30d'] : [];
        $tracked = is_array($data['tracked_keyword'] ?? null) ? $data['tracked_keyword'] : null;
        $cannibalization = is_array($data['cannibalization'] ?? null) ? $data['cannibalization'] : [];
        $striking = is_array($data['striking_distance'] ?? null) ? $data['striking_distance'] : [];
        $audit = is_array($data['audit'] ?? null) ? $data['audit'] : null;
        $flags = is_array($data['flags'] ?? null) ? $data['flags'] : [];

        ?>
        <style>
            .ebq-mb{font-size:12px;}
            .ebq-mb .ebq-head{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:10px 0 4px;}
            .ebq-mb .ebq-head:first-child{margin-top:0;}
            .ebq-mb .ebq-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
            .ebq-mb .ebq-cell{border:1px solid #e2e8f0;border-radius:4px;padding:6px 8px;background:#fff;}
            .ebq-mb .ebq-cell .l{font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:.06em;}
            .ebq-mb .ebq-cell .v{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;}
            .ebq-mb .ebq-alert{border:1px solid;border-radius:4px;padding:6px 8px;margin-bottom:6px;}
            .ebq-mb .ebq-alert.amber{border-color:#fde68a;background:#fffbeb;color:#78350f;}
            .ebq-mb .ebq-alert.indigo{border-color:#c7d2fe;background:#eef2ff;color:#312e81;}
            .ebq-mb .ebq-alert.red{border-color:#fecaca;background:#fef2f2;color:#7f1d1d;}
            .ebq-mb .ebq-alert ul{margin:4px 0 0 14px;padding:0;}
            .ebq-mb .ebq-rank{display:inline-block;padding:2px 8px;border-radius:10px;font-weight:700;font-variant-numeric:tabular-nums;}
            .ebq-mb .ebq-rank.top3{background:#d1fae5;color:#047857;}
            .ebq-mb .ebq-rank.top10{background:#dbeafe;color:#1d4ed8;}
            .ebq-mb .ebq-rank.top20{background:#fef3c7;color:#92400e;}
            .ebq-mb .ebq-rank.rest{background:#f1f5f9;color:#334155;}
        </style>
        <div class="ebq-mb">
            <p class="ebq-head"><?php esc_html_e('Search performance · last 30d', 'ebq-seo'); ?></p>
            <div class="ebq-grid">
                <div class="ebq-cell"><div class="l">Clicks</div><div class="v"><?php echo esc_html(number_format_i18n((int) ($totals30['clicks'] ?? 0))); ?></div></div>
                <div class="ebq-cell"><div class="l">Impressions</div><div class="v"><?php echo esc_html(number_format_i18n((int) ($totals30['impressions'] ?? 0))); ?></div></div>
                <div class="ebq-cell"><div class="l">Avg position</div><div class="v"><?php echo esc_html((string) ($totals30['position'] ?? '—')); ?></div></div>
                <div class="ebq-cell"><div class="l">CTR</div><div class="v"><?php echo esc_html($totals30['ctr'] !== null && $totals30['ctr'] !== '' ? $totals30['ctr'].'%' : '—'); ?></div></div>
            </div>

            <?php if ($tracked): ?>
                <p class="ebq-head"><?php esc_html_e('Rank tracking', 'ebq-seo'); ?></p>
                <?php
                    $pos = $tracked['current_position'] ?? null;
                    $rankClass = 'rest';
                    if ($pos) {
                        if ($pos <= 3) $rankClass = 'top3';
                        elseif ($pos <= 10) $rankClass = 'top10';
                        elseif ($pos <= 20) $rankClass = 'top20';
                    }
                ?>
                <div>
                    <span class="ebq-rank <?php echo esc_attr($rankClass); ?>"><?php echo esc_html($pos ? '#'.$pos : __('Unranked', 'ebq-seo')); ?></span>
                    <?php if (! empty($tracked['position_change'])): ?>
                        <?php $c = (int) $tracked['position_change']; ?>
                        <span style="margin-left:6px;color:<?php echo $c > 0 ? '#047857' : '#b91c1c'; ?>;font-size:11px;">
                            <?php echo $c > 0 ? '▲'.$c : '▼'.abs($c); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p style="font-size:11px;color:#64748b;margin:4px 0 0;">"<?php echo esc_html((string) $tracked['keyword']); ?>"</p>
            <?php endif; ?>

            <?php if (! empty($flags['cannibalized']) && ! empty($cannibalization)): ?>
                <p class="ebq-head"><?php esc_html_e('Cannibalization', 'ebq-seo'); ?></p>
                <div class="ebq-alert amber">
                    <strong><?php esc_html_e('Two or more of your pages split clicks for these queries.', 'ebq-seo'); ?></strong>
                    <ul>
                        <?php foreach (array_slice($cannibalization, 0, 3) as $row): ?>
                            <li>"<?php echo esc_html((string) $row['query']); ?>"
                                <?php if (! empty($row['is_primary_this_page'])): ?>
                                    <em><?php esc_html_e('(primary)', 'ebq-seo'); ?></em>
                                <?php else: ?>
                                    <em style="color:#b91c1c;"><?php esc_html_e('(weaker)', 'ebq-seo'); ?></em>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (! empty($flags['striking_distance']) && ! empty($striking)): ?>
                <p class="ebq-head"><?php esc_html_e('Striking distance', 'ebq-seo'); ?></p>
                <div class="ebq-alert indigo">
                    <strong><?php esc_html_e('Queries at positions 5–20 with below-curve CTR.', 'ebq-seo'); ?></strong>
                    <ul>
                        <?php foreach (array_slice($striking, 0, 5) as $row): ?>
                            <li>"<?php echo esc_html((string) $row['query']); ?>"
                                <span style="color:#64748b;">#<?php echo esc_html((string) $row['position']); ?>
                                · <?php echo esc_html(number_format_i18n((int) $row['impressions'])); ?> impr
                                · <?php echo esc_html((string) $row['ctr']); ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($audit): ?>
                <p class="ebq-head"><?php esc_html_e('Latest audit', 'ebq-seo'); ?></p>
                <div class="ebq-grid">
                    <div class="ebq-cell"><div class="l">Perf mobile</div><div class="v"><?php echo esc_html((string) ($audit['performance_score_mobile'] ?? '—')); ?></div></div>
                    <div class="ebq-cell"><div class="l">Perf desktop</div><div class="v"><?php echo esc_html((string) ($audit['performance_score_desktop'] ?? '—')); ?></div></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
