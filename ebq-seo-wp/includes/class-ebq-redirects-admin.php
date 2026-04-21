<?php
/**
 * Redirections admin page — list, add/edit, delete, bulk, CSV import/export.
 * Renders under Settings → EBQ Redirects.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Redirects_Admin
{
    public const MENU_SLUG = 'ebq-redirects';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_ebq_redirect_save', [$this, 'handle_save']);
        add_action('admin_post_ebq_redirect_delete', [$this, 'handle_delete']);
        add_action('admin_post_ebq_redirect_bulk', [$this, 'handle_bulk']);
        add_action('admin_post_ebq_redirect_import', [$this, 'handle_import']);
        add_action('admin_post_ebq_redirect_export', [$this, 'handle_export']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('EBQ Redirects', 'ebq-seo'),
            __('EBQ Redirects', 'ebq-seo'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $editing = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $status = isset($_GET['ebq_status']) ? sanitize_key((string) wp_unslash($_GET['ebq_status'])) : '';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('EBQ Redirects', 'ebq-seo'); ?></h1>
            <a href="<?php echo esc_url(admin_url('options-general.php?page='.self::MENU_SLUG.'&edit=new')); ?>" class="page-title-action"><?php esc_html_e('Add new', 'ebq-seo'); ?></a>
            <hr class="wp-header-end" />

            <?php $this->render_status_notice($status); ?>

            <?php if ($editing > 0 || $editing === 'new' || $editing === -1 || isset($_GET['edit'])): ?>
                <?php $this->render_form($editing === 'new' || $editing === -1 ? 0 : $editing); ?>
                <?php return; ?>
            <?php endif; ?>

            <?php $this->render_table(); ?>

            <hr style="margin:22px 0;" />

            <?php $this->render_tools(); ?>
        </div>
        <?php
    }

    private function render_status_notice(string $status): void
    {
        $map = [
            'saved' => ['updated', __('Redirect saved.', 'ebq-seo')],
            'deleted' => ['updated', __('Redirect deleted.', 'ebq-seo')],
            'bulk_deleted' => ['updated', __('Selected redirects deleted.', 'ebq-seo')],
            'imported' => ['updated', __('Redirects imported.', 'ebq-seo')],
            'import_failed' => ['error', __('Could not parse the CSV file.', 'ebq-seo')],
            'invalid' => ['error', __('Missing or invalid source/target.', 'ebq-seo')],
            'bad_regex' => ['error', __('Invalid regular expression.', 'ebq-seo')],
        ];
        if (! isset($map[$status])) {
            return;
        }
        [$level, $message] = $map[$status];
        printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($level), esc_html($message));
    }

    private function render_form(int $post_id): void
    {
        $row = [
            'source' => '',
            'target' => '',
            'type' => EBQ_Redirects::TYPE_301,
            'regex' => false,
            'notes' => '',
        ];
        if ($post_id > 0 && get_post_type($post_id) === EBQ_Redirects::CPT) {
            $row['source'] = (string) get_post_meta($post_id, '_ebq_r_source', true);
            $row['target'] = (string) get_post_meta($post_id, '_ebq_r_target', true);
            $row['type'] = (int) get_post_meta($post_id, '_ebq_r_type', true);
            $row['regex'] = (bool) get_post_meta($post_id, '_ebq_r_regex', true);
            $row['notes'] = (string) get_post_meta($post_id, '_ebq_r_notes', true);
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:760px;">
            <input type="hidden" name="action" value="ebq_redirect_save" />
            <input type="hidden" name="id" value="<?php echo esc_attr((string) $post_id); ?>" />
            <?php wp_nonce_field('ebq_redirect_save'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ebq_r_source"><?php esc_html_e('Source', 'ebq-seo'); ?></label></th>
                    <td>
                        <input type="text" id="ebq_r_source" name="source" class="regular-text code"
                            value="<?php echo esc_attr($row['source']); ?>"
                            placeholder="/old-page or ^/old-cat/(.*)$" required />
                        <p class="description"><?php esc_html_e('Path that visitors are hitting. Begin with / for literal; any regex requires the Regex toggle below.', 'ebq-seo'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ebq_r_target"><?php esc_html_e('Target', 'ebq-seo'); ?></label></th>
                    <td>
                        <input type="text" id="ebq_r_target" name="target" class="regular-text code"
                            value="<?php echo esc_attr($row['target']); ?>"
                            placeholder="/new-page or https://…/new-page or /new-cat/$1" />
                        <p class="description"><?php esc_html_e('Where to send the user. Leave empty only for type 410 (Gone).', 'ebq-seo'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ebq_r_type"><?php esc_html_e('Type', 'ebq-seo'); ?></label></th>
                    <td>
                        <select name="type" id="ebq_r_type">
                            <?php foreach ([
                                EBQ_Redirects::TYPE_301 => '301 — '.__('Permanent (recommended)', 'ebq-seo'),
                                EBQ_Redirects::TYPE_302 => '302 — '.__('Temporary', 'ebq-seo'),
                                EBQ_Redirects::TYPE_307 => '307 — '.__('Temporary, keep method', 'ebq-seo'),
                                EBQ_Redirects::TYPE_410 => '410 — '.__('Gone (no target)', 'ebq-seo'),
                            ] as $code => $label): ?>
                                <option value="<?php echo esc_attr((string) $code); ?>" <?php selected($row['type'], $code); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Regex', 'ebq-seo'); ?></th>
                    <td>
                        <label><input type="checkbox" name="regex" value="1" <?php checked($row['regex']); ?> />
                            <?php esc_html_e('Treat source as a PCRE pattern (you can use $1, $2… in target).', 'ebq-seo'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ebq_r_notes"><?php esc_html_e('Notes', 'ebq-seo'); ?></label></th>
                    <td>
                        <textarea id="ebq_r_notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($row['notes']); ?></textarea>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save redirect', 'ebq-seo'); ?></button>
                <a href="<?php echo esc_url(admin_url('options-general.php?page='.self::MENU_SLUG)); ?>" class="button"><?php esc_html_e('Cancel', 'ebq-seo'); ?></a>
            </p>
        </form>
        <?php
    }

    private function render_table(): void
    {
        $rows = (new EBQ_Redirects())->all_redirects();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ebq_redirect_bulk" />
            <?php wp_nonce_field('ebq_redirect_bulk'); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action">
                        <option value=""><?php esc_html_e('Bulk actions', 'ebq-seo'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'ebq-seo'); ?></option>
                    </select>
                    <button type="submit" class="button action"><?php esc_html_e('Apply', 'ebq-seo'); ?></button>
                </div>
                <div class="alignright">
                    <span style="color:#64748b;font-size:12px;"><?php echo esc_html(sprintf(_n('%d redirect', '%d redirects', count($rows), 'ebq-seo'), count($rows))); ?></span>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="ebq_r_cb_all" /></td>
                        <th><?php esc_html_e('Source', 'ebq-seo'); ?></th>
                        <th><?php esc_html_e('Target', 'ebq-seo'); ?></th>
                        <th><?php esc_html_e('Type', 'ebq-seo'); ?></th>
                        <th><?php esc_html_e('Hits', 'ebq-seo'); ?></th>
                        <th><?php esc_html_e('Last hit', 'ebq-seo'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" style="padding:20px;text-align:center;color:#64748b;"><?php esc_html_e('No redirects yet. Add one above, or import from a CSV / Yoast / Rank Math below.', 'ebq-seo'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo esc_attr((string) $row['id']); ?>" /></th>
                            <td>
                                <code style="word-break:break-all;"><?php echo esc_html((string) $row['source']); ?></code>
                                <?php if ($row['regex']): ?>
                                    <span style="margin-left:6px;padding:1px 6px;background:#e0e7ff;color:#3730a3;border-radius:3px;font-size:10px;">regex</span>
                                <?php endif; ?>
                            </td>
                            <td><code style="word-break:break-all;"><?php echo esc_html((string) $row['target']); ?></code></td>
                            <td><?php echo esc_html((string) $row['type']); ?></td>
                            <td style="tabular-nums;"><?php echo esc_html(number_format_i18n((int) $row['hits'])); ?></td>
                            <td style="color:#64748b;font-size:11px;">
                                <?php echo $row['last_hit'] !== '' ? esc_html((string) $row['last_hit']) : '—'; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('options-general.php?page='.self::MENU_SLUG.'&edit='.$row['id'])); ?>" class="button button-small"><?php esc_html_e('Edit', 'ebq-seo'); ?></a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebq_redirect_delete&id='.$row['id']), 'ebq_redirect_delete_'.$row['id'])); ?>"
                                    class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this redirect?', 'ebq-seo')); ?>');">
                                    <?php esc_html_e('Delete', 'ebq-seo'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <script>
            (function(){
                var cb = document.getElementById('ebq_r_cb_all');
                if(!cb) return;
                cb.addEventListener('change', function(){
                    document.querySelectorAll('input[name="ids[]"]').forEach(function(x){ x.checked = cb.checked; });
                });
            })();
        </script>
        <?php
    }

    private function render_tools(): void
    {
        ?>
        <h2 style="margin-top:22px;"><?php esc_html_e('Import / export', 'ebq-seo'); ?></h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;max-width:960px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px;">
                <h3 style="margin:0 0 6px;font-size:14px;"><?php esc_html_e('Import CSV', 'ebq-seo'); ?></h3>
                <p style="margin:0 0 8px;color:#64748b;font-size:12px;"><?php esc_html_e('Columns (with header): source,target,type,regex,notes. Existing sources are updated.', 'ebq-seo'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ebq_redirect_import" />
                    <?php wp_nonce_field('ebq_redirect_import'); ?>
                    <input type="file" name="csv" accept=".csv,text/csv" required />
                    <button type="submit" class="button button-primary" style="margin-top:6px;"><?php esc_html_e('Import', 'ebq-seo'); ?></button>
                </form>
            </div>

            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px;">
                <h3 style="margin:0 0 6px;font-size:14px;"><?php esc_html_e('Export CSV', 'ebq-seo'); ?></h3>
                <p style="margin:0 0 8px;color:#64748b;font-size:12px;"><?php esc_html_e('Download every redirect as a CSV, including hit counts.', 'ebq-seo'); ?></p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebq_redirect_export'), 'ebq_redirect_export')); ?>" class="button">
                    <?php esc_html_e('Download redirects.csv', 'ebq-seo'); ?>
                </a>
            </div>
        </div>
        <?php

        // Yoast/Rank Math import banner (if detected and not already migrated).
        if (class_exists('EBQ_Redirects_Importer')) {
            (new EBQ_Redirects_Importer())->render_import_banner();
        }
    }

    public function handle_save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_redirect_save');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $source = isset($_POST['source']) ? (string) wp_unslash($_POST['source']) : '';
        $target = isset($_POST['target']) ? (string) wp_unslash($_POST['target']) : '';
        $type = isset($_POST['type']) ? (int) $_POST['type'] : EBQ_Redirects::TYPE_301;
        $regex = ! empty($_POST['regex']);
        $notes = isset($_POST['notes']) ? (string) wp_unslash($_POST['notes']) : '';

        if ($source === '' || ($target === '' && $type !== EBQ_Redirects::TYPE_410)) {
            wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=invalid'));
            exit;
        }
        if ($regex) {
            $pattern = '#'.str_replace('#', '\\#', $source).'#i';
            if (@preg_match($pattern, '') === false) {
                wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=bad_regex'));
                exit;
            }
        }

        (new EBQ_Redirects())->upsert([
            'source' => $source,
            'target' => $target,
            'type' => $type,
            'regex' => $regex,
            'notes' => $notes,
        ], $id > 0 ? $id : null);

        wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=saved'));
        exit;
    }

    public function handle_delete(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        check_admin_referer('ebq_redirect_delete_'.$id);
        (new EBQ_Redirects())->delete($id);
        wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=deleted'));
        exit;
    }

    public function handle_bulk(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_redirect_bulk');

        $action = isset($_POST['bulk_action']) ? sanitize_key((string) wp_unslash($_POST['bulk_action'])) : '';
        $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];
        if ($action === 'delete' && ! empty($ids)) {
            $svc = new EBQ_Redirects();
            foreach ($ids as $id) {
                $svc->delete((int) $id);
            }
            wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=bulk_deleted'));
            exit;
        }
        wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG));
        exit;
    }

    public function handle_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_redirect_import');

        if (empty($_FILES['csv']['tmp_name']) || ! is_uploaded_file((string) $_FILES['csv']['tmp_name'])) {
            wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=import_failed'));
            exit;
        }

        $path = (string) $_FILES['csv']['tmp_name'];
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=import_failed'));
            exit;
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=import_failed'));
            exit;
        }
        $header = array_map(static fn ($h): string => strtolower(trim((string) $h)), $header);

        $svc = new EBQ_Redirects();
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $row = array_combine($header, array_pad($row, count($header), ''));
            if (! is_array($row)) {
                continue;
            }
            $source = trim((string) ($row['source'] ?? ''));
            if ($source === '') {
                continue;
            }
            $target = trim((string) ($row['target'] ?? ''));
            $type = (int) ($row['type'] ?? EBQ_Redirects::TYPE_301);
            $regex = ! empty($row['regex']) && $row['regex'] !== '0' && strtolower((string) $row['regex']) !== 'false';
            $notes = (string) ($row['notes'] ?? '');

            $existing = $svc->find_by_source($source);
            $svc->upsert([
                'source' => $source,
                'target' => $target,
                'type' => $type,
                'regex' => $regex,
                'notes' => $notes,
            ], $existing);
            $count++;
        }
        fclose($handle);

        wp_safe_redirect(admin_url('options-general.php?page='.self::MENU_SLUG.'&ebq_status=imported&count='.$count));
        exit;
    }

    public function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_redirect_export');

        $rows = (new EBQ_Redirects())->all_redirects();

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ebq-redirects-'.date('Ymd-His').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['source', 'target', 'type', 'regex', 'hits', 'last_hit', 'notes']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['source'],
                $row['target'],
                $row['type'],
                $row['regex'] ? '1' : '0',
                $row['hits'],
                $row['last_hit'],
                $row['notes'],
            ]);
        }
        fclose($out);
        exit;
    }
}
