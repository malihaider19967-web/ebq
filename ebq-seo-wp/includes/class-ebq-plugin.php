<?php
/**
 * Singleton wiring every subsystem into WordPress hooks.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        (new EBQ_Connect())->register();
        (new EBQ_Updater())->register();
        (new EBQ_Settings())->register();
        (new EBQ_Rest_Proxy())->register();
        (new EBQ_Post_Column())->register();
        (new EBQ_Dashboard_Widget())->register();
        (new EBQ_Gutenberg_Sidebar())->register();
    }

    public static function api_client(): EBQ_Api_Client
    {
        return new EBQ_Api_Client((string) get_option('ebq_site_token', ''));
    }

    public static function is_configured(): bool
    {
        return (string) get_option('ebq_site_token', '') !== '';
    }
}
