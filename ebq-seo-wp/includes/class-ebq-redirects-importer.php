<?php
/**
 * Detects existing Yoast Premium / Rank Math redirect data and migrates it
 * into the `ebq_redirect` CPT on demand. Idempotent — running twice only
 * updates rows with the same source.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Redirects_Importer
{
    public function register(): void
    {
        add_action('admin_post_ebq_redirect_import_yoast', [$this, 'run_yoast_import']);
        add_action('admin_post_ebq_redirect_import_rankmath', [$this, 'run_rankmath_import']);
    }

    public function render_import_banner(): void
    {
        $yoast = count($this->yoast_rules());
        $rankmath = count($this->rankmath_rules());
        if ($yoast === 0 && $rankmath === 0) {
            return;
        }
        ?>
        <div style="margin-top:18px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;padding:14px;max-width:960px;">
            <h3 style="margin:0 0 6px;font-size:14px;color:#3730a3;"><?php esc_html_e('Migrate from another plugin', 'ebq-seo'); ?></h3>
            <p style="margin:0 0 10px;color:#312e81;font-size:12px;">
                <?php esc_html_e('We detected redirects from a different SEO plugin. Click to migrate them — existing sources are updated, new ones are added.', 'ebq-seo'); ?>
            </p>
            <?php if ($yoast > 0): ?>
                <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebq_redirect_import_yoast'), 'ebq_redirect_import_yoast')); ?>">
                    <?php echo esc_html(sprintf(__('Import %d redirect(s) from Yoast Premium', 'ebq-seo'), $yoast)); ?>
                </a>
            <?php endif; ?>
            <?php if ($rankmath > 0): ?>
                <a class="button button-secondary" style="margin-left:6px;" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebq_redirect_import_rankmath'), 'ebq_redirect_import_rankmath')); ?>">
                    <?php echo esc_html(sprintf(__('Import %d redirect(s) from Rank Math', 'ebq-seo'), $rankmath)); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    public function run_yoast_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_redirect_import_yoast');
        $this->import($this->yoast_rules());
        wp_safe_redirect(admin_url('admin.php?page='.EBQ_Redirects_Admin::MENU_SLUG.'&ebq_status=imported'));
        exit;
    }

    public function run_rankmath_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_redirect_import_rankmath');
        $this->import($this->rankmath_rules());
        wp_safe_redirect(admin_url('admin.php?page='.EBQ_Redirects_Admin::MENU_SLUG.'&ebq_status=imported'));
        exit;
    }

    /**
     * @param  list<array{source:string,target:string,type:int,regex:bool,notes:string}>  $rules
     */
    private function import(array $rules): void
    {
        if (empty($rules)) {
            return;
        }
        $svc = new EBQ_Redirects();
        foreach ($rules as $rule) {
            $existing = $svc->find_by_source($rule['source']);
            $svc->upsert($rule, $existing);
        }
    }

    /**
     * @return list<array{source:string,target:string,type:int,regex:bool,notes:string}>
     */
    private function yoast_rules(): array
    {
        // Yoast Premium stores redirects in `wpseo-premium-redirects-base` option (plain) and
        // `wpseo-premium-redirects-base_regex` (regex). Both are serialized arrays of arrays.
        $plain = get_option('wpseo-premium-redirects-base');
        $regex = get_option('wpseo-premium-redirects-base_regex');
        $rules = [];

        foreach ([['plain', $plain, false], ['regex', $regex, true]] as [$kind, $store, $is_regex]) {
            if (! is_array($store)) {
                continue;
            }
            foreach ($store as $source => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rules[] = [
                    'source' => (string) $source,
                    'target' => isset($row['url']) ? (string) $row['url'] : '',
                    'type' => isset($row['type']) ? (int) $row['type'] : 301,
                    'regex' => $is_regex,
                    'notes' => 'Imported from Yoast Premium ('.$kind.')',
                ];
            }
        }

        return $rules;
    }

    /**
     * @return list<array{source:string,target:string,type:int,regex:bool,notes:string}>
     */
    private function rankmath_rules(): array
    {
        global $wpdb;
        $rules = [];
        $table = $wpdb->prefix.'rank_math_redirections';
        $exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return [];
        }

        $rows = $wpdb->get_results("SELECT sources, url_to, header_code, status FROM {$table} WHERE status = 'active'", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            $sources = maybe_unserialize($row['sources']);
            if (! is_array($sources)) {
                continue;
            }
            $target = (string) ($row['url_to'] ?? '');
            $type = (int) ($row['header_code'] ?? 301);
            foreach ($sources as $src) {
                if (! is_array($src) || empty($src['pattern'])) {
                    continue;
                }
                $pattern = (string) $src['pattern'];
                $comparison = (string) ($src['comparison'] ?? 'exact');
                $rules[] = [
                    'source' => $pattern,
                    'target' => $target,
                    'type' => $type,
                    'regex' => $comparison === 'regex',
                    'notes' => 'Imported from Rank Math',
                ];
            }
        }

        return $rules;
    }
}
