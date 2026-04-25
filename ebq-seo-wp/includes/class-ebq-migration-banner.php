<?php
/**
 * One-time admin banner that nudges site owners coming from another SEO
 * plugin to import their data into EBQ. Detects Yoast / Rank Math via the
 * shared `EBQ_Meta_Output::another_seo_plugin_is_active()` helper plus a
 * fallback meta-table check (so users who already deactivated the source
 * plugin still see the banner). Dismissible per-site; never shown twice
 * for the same source once the migration completes.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Migration_Banner
{
    public const DISMISS_OPTION = 'ebq_migration_dismissed';
    public const DISMISS_ACTION = 'ebq_migration_dismiss';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'maybe_render']);
        add_action('admin_post_' . self::DISMISS_ACTION, [$this, 'handle_dismiss']);
    }

    public function maybe_render(): void
    {
        if (! current_user_can('manage_options')) return;
        if ((string) get_option(self::DISMISS_OPTION, '0') === '1') return;

        // Don't nag on the EBQ HQ pages themselves — the same migration
        // card lives one click away there.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array($screen->id, ['toplevel_page_ebq-hq', 'ebq-hq_page_ebq-seo'], true)) {
            return;
        }

        $sources = EBQ_Migration::available_sources();
        if (empty($sources)) return;

        // Hide once every available source's migration has completed.
        $any_pending = false;
        foreach ($sources as $src) {
            if ((int) get_option('ebq_migration_completed_' . $src->id(), 0) === 0) {
                $any_pending = true;
                break;
            }
        }
        if (! $any_pending) return;

        $labels = array_map(fn ($s) => $s->label(), $sources);
        $list = implode(' / ', $labels);
        $settings_url = EBQ_Settings::url('#ebq-migrate');
        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::DISMISS_ACTION),
            self::DISMISS_ACTION
        );
        ?>
        <div class="notice notice-info" style="border-left-color:#5b3df5;">
            <p style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:.6em 0;">
                <span style="display:inline-grid;place-items:center;width:26px;height:26px;border-radius:6px;background:#ede9fe;color:#5b3df5;font-weight:700;flex-shrink:0;">E</span>
                <span style="flex:1;min-width:240px;">
                    <strong><?php echo esc_html(sprintf(
                        /* translators: %s — comma-separated list of source plugin names */
                        __('Switching from %s?', 'ebq-seo'),
                        $list
                    )); ?></strong>
                    <?php esc_html_e('Import your existing focus keyphrases, social meta, schemas, and breadcrumb overrides into EBQ in one click.', 'ebq-seo'); ?>
                </span>
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                    <?php esc_html_e('Open migration tool', 'ebq-seo'); ?> →
                </a>
                <a href="<?php echo esc_url($dismiss_url); ?>" class="button-link" style="color:#64748b;">
                    <?php esc_html_e('Dismiss', 'ebq-seo'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function handle_dismiss(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer(self::DISMISS_ACTION);
        update_option(self::DISMISS_OPTION, '1', false);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
