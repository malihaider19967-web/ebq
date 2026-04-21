<?php
/**
 * Admin menu + settings page: paste API base + site token, test connection.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Settings
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
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

    public function register_settings(): void
    {
        register_setting('ebq_seo', 'ebq_api_base', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
        register_setting('ebq_seo', 'ebq_site_token', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('ebq_seo', 'ebq_challenge_code', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $dashboard = null;
        $status = null;

        if (EBQ_Plugin::is_configured()) {
            $dashboard = EBQ_Plugin::api_client()->get_dashboard();
            $status = ! empty($dashboard['website_id']) ? 'connected' : 'error';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('EBQ SEO', 'ebq-seo'); ?></h1>
            <p><?php esc_html_e('Connect this WordPress site to EBQ so editors see insights inside Gutenberg.', 'ebq-seo'); ?></p>

            <?php if ($status === 'connected'): ?>
                <div class="notice notice-success"><p>
                    <?php echo esc_html(sprintf(__('Connected to %s', 'ebq-seo'), $dashboard['domain'] ?? '')); ?>
                </p></div>
            <?php elseif ($status === 'error'): ?>
                <div class="notice notice-error"><p>
                    <?php esc_html_e('Could not reach EBQ with the configured token. Double-check the API base URL and regenerate the token if needed.', 'ebq-seo'); ?>
                </p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('ebq_seo'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ebq_api_base"><?php esc_html_e('API base URL', 'ebq-seo'); ?></label></th>
                        <td>
                            <input name="ebq_api_base" id="ebq_api_base" type="url" class="regular-text" value="<?php echo esc_attr((string) get_option('ebq_api_base', 'https://app.ebq.io')); ?>" />
                            <p class="description"><?php esc_html_e('Your EBQ workspace URL. Defaults to https://app.ebq.io.', 'ebq-seo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ebq_challenge_code"><?php esc_html_e('Verification code', 'ebq-seo'); ?></label></th>
                        <td>
                            <input name="ebq_challenge_code" id="ebq_challenge_code" type="text" class="regular-text code" value="<?php echo esc_attr((string) get_option('ebq_challenge_code', '')); ?>" placeholder="ebq-xxxxxxxxxxxxxx" />
                            <p class="description">
                                <?php echo wp_kses_post(sprintf(
                                    __('Generate this in EBQ under <code>Settings → Integrations → WordPress plugin</code>. While it\'s saved here, this site serves it at <code>%s</code>.', 'ebq-seo'),
                                    esc_url(home_url('/.well-known/ebq-verification.txt'))
                                )); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ebq_site_token"><?php esc_html_e('API token', 'ebq-seo'); ?></label></th>
                        <td>
                            <input name="ebq_site_token" id="ebq_site_token" type="password" class="regular-text code" autocomplete="off" value="<?php echo esc_attr((string) get_option('ebq_site_token', '')); ?>" />
                            <p class="description"><?php esc_html_e('Paste the token shown by EBQ after successful verification.', 'ebq-seo'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
