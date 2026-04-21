<?php
/**
 * Admin menu + settings page. Single Connect button — no fields, no pasting.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Settings
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('EBQ SEO', 'ebq-seo'),
            __('EBQ SEO', 'ebq-seo'),
            'manage_options',
            'ebq-seo',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $status = isset($_GET['ebq_status']) ? sanitize_key((string) wp_unslash($_GET['ebq_status'])) : '';
        $update_status = isset($_GET['ebq_update']) ? sanitize_key((string) wp_unslash($_GET['ebq_update'])) : '';
        $latest_from_query = isset($_GET['latest']) ? sanitize_text_field((string) wp_unslash($_GET['latest'])) : '';
        $connected = EBQ_Plugin::is_configured();
        $domain = (string) get_option('ebq_website_domain', '');
        $website_id = (int) get_option('ebq_website_id', 0);
        $last_error = (string) get_option('ebq_last_connect_error', '');
        $has_new_connect_flow = method_exists('EBQ_Connect', 'start_url');
        $updater = class_exists('EBQ_Updater') ? new EBQ_Updater() : null;
        $update_meta = $updater ? $updater->fetch_meta() : null;
        $latest_version = is_array($update_meta) ? (string) ($update_meta['version'] ?? '') : '';
        $has_update = $latest_version !== '' && version_compare($latest_version, EBQ_SEO_VERSION, '>');
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('EBQ SEO', 'ebq-seo'); ?>
                <span style="font-size:12px;font-weight:400;color:#94a3b8;margin-left:8px;vertical-align:middle;">v<?php echo esc_html(EBQ_SEO_VERSION); ?></span>
            </h1>

            <?php if (! $has_new_connect_flow): ?>
                <div class="notice notice-error"><p>
                    <?php esc_html_e('You\'re running an older version of the plugin that uses a now-deprecated connect flow. Please uninstall and re-upload the latest ZIP from your EBQ workspace.', 'ebq-seo'); ?>
                </p></div>
            <?php endif; ?>

            <?php $this->render_notice($status); ?>

            <?php if (! $connected && $last_error !== ''): ?>
                <div class="notice notice-error" style="padding:12px;">
                    <p style="margin:0 0 6px;font-weight:600;">
                        <?php esc_html_e('Last connection attempt failed', 'ebq-seo'); ?>
                    </p>
                    <code style="display:block;padding:8px;background:#fef2f2;border-radius:4px;word-break:break-all;font-size:11px;">
                        <?php echo esc_html($last_error); ?>
                    </code>
                    <p style="margin:8px 0 0;font-size:11px;color:#7f1d1d;">
                        <?php esc_html_e('Click "Connect to EBQ" again below and approve in EBQ. If this keeps happening, check that your browser isn\'t blocking third-party cookies on the EBQ domain during the redirect.', 'ebq-seo'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div style="max-width:560px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:20px;margin-top:16px;">
                <?php if ($connected): ?>
                    <p style="margin:0 0 4px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#64748b;">
                        <?php esc_html_e('Connected', 'ebq-seo'); ?>
                    </p>
                    <h2 style="margin:0 0 6px;font-size:18px;">
                        <?php echo esc_html($domain ?: __('EBQ workspace', 'ebq-seo')); ?>
                    </h2>
                    <p style="margin:0 0 16px;color:#64748b;font-size:13px;">
                        <?php echo esc_html(sprintf(__('Website #%d · insights are live in the editor, post list, and dashboard.', 'ebq-seo'), $website_id)); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(EBQ_Api_Client::base_url() . '/reports'); ?>" class="button button-primary" target="_blank" rel="noopener">
                            <?php esc_html_e('Open EBQ Reports', 'ebq-seo'); ?>
                        </a>
                        <a href="<?php echo esc_url(EBQ_Connect::disconnect_url()); ?>" class="button" style="margin-left:6px;">
                            <?php esc_html_e('Disconnect', 'ebq-seo'); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <h2 style="margin:0 0 6px;font-size:18px;"><?php esc_html_e('Connect this site to EBQ', 'ebq-seo'); ?></h2>
                    <p style="margin:0 0 16px;color:#64748b;font-size:13px;">
                        <?php esc_html_e('One click. You\'ll log in to EBQ, pick which website to link, and come back — the token is exchanged for you.', 'ebq-seo'); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(EBQ_Connect::start_url()); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('Connect to EBQ →', 'ebq-seo'); ?>
                        </a>
                    </p>
                    <p style="margin-top:14px;color:#64748b;font-size:12px;">
                        <?php echo wp_kses_post(sprintf(
                            __('No EBQ account yet? <a href="%s" target="_blank" rel="noopener">Create one free</a> — takes under a minute.', 'ebq-seo'),
                            esc_url(EBQ_Api_Client::base_url() . '/register')
                        )); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php // Updates card ?>
            <div style="max-width:560px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px 20px;margin-top:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div style="min-width:0;">
                        <p style="margin:0 0 2px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#64748b;"><?php esc_html_e('Plugin version', 'ebq-seo'); ?></p>
                        <?php if ($has_update): ?>
                            <p style="margin:0;font-size:14px;">
                                <strong><?php echo esc_html(EBQ_SEO_VERSION); ?></strong>
                                <span style="color:#64748b;">→</span>
                                <strong style="color:#047857;">v<?php echo esc_html($latest_version); ?></strong>
                                <span style="margin-left:6px;padding:2px 6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;background:#d1fae5;color:#047857;border-radius:10px;"><?php esc_html_e('Update available', 'ebq-seo'); ?></span>
                            </p>
                        <?php else: ?>
                            <p style="margin:0;font-size:14px;">
                                <strong><?php echo esc_html(EBQ_SEO_VERSION); ?></strong>
                                <?php if ($latest_version !== ''): ?>
                                    <span style="margin-left:6px;color:#64748b;font-size:11px;"><?php esc_html_e('— you\'re on the latest', 'ebq-seo'); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($has_update): ?>
                            <a href="<?php echo esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(plugin_basename(EBQ_SEO_FILE))), 'upgrade-plugin_'.plugin_basename(EBQ_SEO_FILE))); ?>" class="button button-primary">
                                <?php echo esc_html(sprintf(__('Install v%s now', 'ebq-seo'), $latest_version)); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(EBQ_Updater::check_url()); ?>" class="button">
                            <?php esc_html_e('Check for updates', 'ebq-seo'); ?>
                        </a>
                    </div>
                </div>
                <?php if ($update_status === 'up_to_date'): ?>
                    <p style="margin:10px 0 0;color:#047857;font-size:12px;">✓ <?php esc_html_e('You\'re running the latest version.', 'ebq-seo'); ?></p>
                <?php elseif ($update_status === 'update_available'): ?>
                    <p style="margin:10px 0 0;color:#047857;font-size:12px;">
                        <?php echo esc_html(sprintf(__('Update v%s found. Click "Install v%s now" to apply.', 'ebq-seo'), $latest_from_query, $latest_from_query)); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (defined('EBQ_API_BASE')): ?>
                <p style="margin-top:16px;color:#94a3b8;font-size:11px;">
                    <?php echo esc_html(sprintf(__('Advanced: EBQ_API_BASE is defined in wp-config.php as %s.', 'ebq-seo'), (string) EBQ_API_BASE)); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_notice(string $status): void
    {
        if ($status === '') {
            return;
        }
        $map = [
            'connected' => ['success', __('Connected successfully. Insights are now live.', 'ebq-seo')],
            'disconnected' => ['warning', __('Disconnected. Local token cleared.', 'ebq-seo')],
            'state_mismatch' => ['error', __('Connection rejected — the returned state did not match what this site issued. Try again.', 'ebq-seo')],
            'bad_token' => ['error', __('EBQ sent back an empty or invalid token. Try again.', 'ebq-seo')],
        ];
        if (! isset($map[$status])) {
            return;
        }
        [$level, $message] = $map[$status];
        printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($level), esc_html($message));
    }
}
