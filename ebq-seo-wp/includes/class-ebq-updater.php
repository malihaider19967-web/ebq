<?php
/**
 * Self-update: hooks into WordPress's native plugin-update flow so "Update
 * now" works from the Plugins screen, and adds a "Check for updates" button
 * on our settings page that forces a fresh fetch.
 *
 * Version metadata is served by EBQ at /wordpress/plugin/version and the ZIP
 * itself at /wordpress/plugin.zip.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Updater
{
    public const TRANSIENT_KEY = 'ebq_update_meta';
    public const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_action('admin_post_ebq_check_updates', [$this, 'force_check']);
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
    }

    public function basename(): string
    {
        return plugin_basename(EBQ_SEO_FILE);
    }

    /**
     * Tells WP there's a newer version available so the Plugins UI shows the
     * usual "Update Now" link.
     */
    public function inject_update(mixed $transient): mixed
    {
        if (! is_object($transient)) {
            return $transient;
        }

        $meta = $this->fetch_meta();
        if (! is_array($meta) || empty($meta['version']) || empty($meta['download_url'])) {
            return $transient;
        }

        $basename = $this->basename();
        if (version_compare((string) $meta['version'], EBQ_SEO_VERSION, '>')) {
            $update = (object) [
                'id' => 'ebq-seo',
                'slug' => 'ebq-seo',
                'plugin' => $basename,
                'new_version' => (string) $meta['version'],
                'url' => (string) ($meta['homepage'] ?? ''),
                'package' => (string) $meta['download_url'],
                'tested' => (string) ($meta['tested'] ?? ''),
                'requires' => (string) ($meta['requires']['wp'] ?? ''),
                'requires_php' => (string) ($meta['requires']['php'] ?? ''),
                'icons' => [],
                'banners' => [],
            ];
            $transient->response[$basename] = $update;
            unset($transient->no_update[$basename]);
        } else {
            $no_update = (object) [
                'id' => 'ebq-seo',
                'slug' => 'ebq-seo',
                'plugin' => $basename,
                'new_version' => EBQ_SEO_VERSION,
                'url' => (string) ($meta['homepage'] ?? ''),
                'package' => '',
            ];
            $transient->no_update[$basename] = $no_update;
            unset($transient->response[$basename]);
        }

        return $transient;
    }

    /**
     * Fills in "View details" for our plugin on the Plugins screen.
     */
    public function plugin_info(mixed $result, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (! is_object($args) || ($args->slug ?? '') !== 'ebq-seo') {
            return $result;
        }

        $meta = $this->fetch_meta();
        if (! is_array($meta)) {
            return $result;
        }

        return (object) [
            'name' => (string) ($meta['name'] ?? 'EBQ SEO'),
            'slug' => 'ebq-seo',
            'version' => (string) ($meta['version'] ?? EBQ_SEO_VERSION),
            'requires' => (string) ($meta['requires']['wp'] ?? ''),
            'tested' => (string) ($meta['tested'] ?? ''),
            'requires_php' => (string) ($meta['requires']['php'] ?? ''),
            'download_link' => (string) ($meta['download_url'] ?? ''),
            'homepage' => (string) ($meta['homepage'] ?? ''),
            'sections' => [
                'description' => 'Cross-signal SEO insights inside WordPress.',
                'changelog' => sprintf('<p>See <a href="%s" target="_blank" rel="noopener">full changelog</a>.</p>', esc_url((string) ($meta['changelog_url'] ?? ''))),
            ],
        ];
    }

    /**
     * "Check for updates" button handler — deletes caches and triggers WP's
     * update-check cycle, then redirects back to settings with a status.
     */
    public function force_check(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to check for updates.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_check_updates');

        $this->clear_cache();
        delete_site_transient('update_plugins');
        wp_update_plugins();

        $meta = $this->fetch_meta(true);
        $latest = is_array($meta) ? (string) ($meta['version'] ?? '') : '';
        $status = ($latest !== '' && version_compare($latest, EBQ_SEO_VERSION, '>'))
            ? 'update_available'
            : 'up_to_date';

        wp_safe_redirect(admin_url('admin.php?page=ebq-seo&ebq_update='.$status.'&latest='.rawurlencode($latest)));
        exit;
    }

    public function clear_cache(mixed $upgrader = null, mixed $hook_extra = null): void
    {
        delete_transient(self::TRANSIENT_KEY);
    }

    public static function check_url(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=ebq_check_updates'),
            'ebq_check_updates'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch_meta(bool $force = false): ?array
    {
        if (! $force) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = EBQ_Api_Client::base_url() . '/wordpress/plugin/version';
        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        set_transient(self::TRANSIENT_KEY, $decoded, self::CACHE_TTL);

        return $decoded;
    }
}
