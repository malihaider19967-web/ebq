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
        $connected = EBQ_Plugin::is_configured();
        $domain = (string) get_option('ebq_website_domain', '');
        $website_id = (int) get_option('ebq_website_id', 0);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('EBQ SEO', 'ebq-seo'); ?></h1>

            <?php $this->render_notice($status); ?>

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
                    <?php $last_error = (string) get_option('ebq_last_connect_error', ''); ?>
                    <?php if ($last_error !== ''): ?>
                        <details style="margin-top:14px;color:#64748b;font-size:11px;">
                            <summary style="cursor:pointer;"><?php esc_html_e('Last connection attempt diagnostics', 'ebq-seo'); ?></summary>
                            <code style="display:block;margin-top:6px;padding:8px;background:#f1f5f9;border-radius:4px;word-break:break-all;"><?php echo esc_html($last_error); ?></code>
                        </details>
                    <?php endif; ?>
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
