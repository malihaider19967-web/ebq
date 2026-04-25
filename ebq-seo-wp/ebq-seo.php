<?php
/**
 * Plugin Name:       EBQ SEO
 * Plugin URI:        https://ebq.io/features
 * Description:       The only SEO plugin your WordPress site needs. Real-data focus keyword, live competitor SERP, cannibalization-aware canonical, CWV-gated publish, plus Yoast-parity on-page surface (meta/social/schema/sitemap/canonical/robots). One-click connect to your EBQ workspace.
 * Version:           2.2.3
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

define('EBQ_SEO_VERSION', '2.2.3');
define('EBQ_SEO_FILE', __FILE__);
define('EBQ_SEO_PATH', plugin_dir_path(__FILE__));
define('EBQ_SEO_URL', plugin_dir_url(__FILE__));

require_once EBQ_SEO_PATH . 'includes/class-ebq-plugin.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-settings.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-api-client.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-connect.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-post-column.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-dashboard-widget.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-rest-proxy.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-gutenberg-sidebar.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-block-editor-metabox.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-meta-box.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-updater.php';
// v2 — Yoast-replacement surface
require_once EBQ_SEO_PATH . 'includes/class-ebq-meta-fields.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-title-template.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-meta-output.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-social-output.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-schema-variables.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-schema-templates.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-schema-output.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-schema-shortcode.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-hq-page.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-sitemap.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-seo-fields-meta-box.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-breadcrumbs.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-analysis-cache.php';
// v2.1 — Redirects
require_once EBQ_SEO_PATH . 'includes/class-ebq-redirects.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-redirects-auto.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-redirects-admin.php';
require_once EBQ_SEO_PATH . 'includes/class-ebq-redirects-importer.php';

register_activation_hook(__FILE__, static function (): void {
    add_option('ebq_site_token', '');
    add_option('ebq_website_id', 0);
    add_option('ebq_website_domain', '');
    add_option('ebq_connect_state', '');
    add_option('ebq_last_connect_error', '');

    // Sitemap rewrite rules need registering before flush; the Sitemap class
    // adds them on init, so we schedule a flush on next request.
    update_option('ebq_flush_rewrites_pending', '1');
});

register_deactivation_hook(__FILE__, static function (): void {
    delete_option('ebq_connect_state');
    delete_option('ebq_last_connect_error');
    flush_rewrite_rules();
});

add_action('wp_loaded', static function (): void {
    if (get_option('ebq_flush_rewrites_pending') === '1') {
        flush_rewrite_rules();
        delete_option('ebq_flush_rewrites_pending');
    }
}, 20);

add_action('init', static function (): void {
    $stored = (string) get_option('ebq_seo_plugin_version', '');
    if ($stored !== EBQ_SEO_VERSION) {
        update_option('ebq_seo_plugin_version', EBQ_SEO_VERSION);
        update_option('ebq_flush_rewrites_pending', '1');
    }
}, 0);

add_action('plugins_loaded', static function (): void {
    EBQ_Plugin::instance()->boot();
});

if (! function_exists('ebq_get_breadcrumbs_html')) {
    /**
     * @param  array<string, mixed>  $args
     */
    function ebq_get_breadcrumbs_html(array $args = []): string
    {
        return EBQ_Breadcrumbs::render_html($args);
    }
}
