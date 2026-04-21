<?php
/**
 * Plugin Name:       EBQ SEO
 * Plugin URI:        https://app.ebq.io/features
 * Description:       Shows EBQ's cross-signal SEO insights (cannibalization, striking distance, rank, audits) inside the Gutenberg editor, the post list, and the WordPress dashboard.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            EBQ
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ebq-seo
 */

if (! defined('ABSPATH')) {
    exit;
}

define('EBQ_SEO_VERSION', '1.0.0');
define('EBQ_SEO_FILE', __FILE__);
define('EBQ_SEO_PATH', plugin_dir_path(__FILE__));
define('EBQ_SEO_URL', plugin_dir_url(__FILE__));

require_once EBQ_SEO_PATH . 'includes/class-ebq-plugin.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-settings.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-api-client.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-verification.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-post-column.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-dashboard-widget.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-rest-proxy.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-gutenberg-sidebar.php';

register_activation_hook(__FILE__, static function (): void {
    add_option('ebq_api_base', 'https://app.ebq.io');
    add_option('ebq_site_token', '');
    add_option('ebq_challenge_code', '');
    add_option('ebq_verified_at', '');
});

add_action('plugins_loaded', static function (): void {
    EBQ_Plugin::instance()->boot();
});
