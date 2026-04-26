<?php
/**
 * Classic Editor meta box.
 *
 * Used to be a plain HTML form. Now hosts the same React app as the block
 * editor sidebar, with an environment flag (window.__EBQ_CLASSIC__) that
 * switches the React data source from @wordpress/data to a DOM-based store.
 *
 * Save path stays form-POST: hidden inputs (rendered below) carry the meta
 * keys, and the existing save() handler picks them up so existing post data
 * round-trips cleanly between editors.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Seo_Fields_Meta_Box
{
    public const NONCE_ACTION = 'ebq_seo_fields_save';
    public const NONCE_FIELD = 'ebq_seo_fields_nonce';

    /** Meta key → form field name (also used by the JS classic adapter). */
    private const FIELDS = [
        '_ebq_title'               => 'ebq_title',
        '_ebq_description'         => 'ebq_description',
        '_ebq_canonical'           => 'ebq_canonical',
        '_ebq_robots_noindex'      => 'ebq_robots_noindex',
        '_ebq_robots_nofollow'     => 'ebq_robots_nofollow',
        '_ebq_robots_advanced'     => 'ebq_robots_advanced',
        '_ebq_focus_keyword'       => 'ebq_focus_keyword',
        '_ebq_og_title'            => 'ebq_og_title',
        '_ebq_og_description'      => 'ebq_og_description',
        '_ebq_og_image'            => 'ebq_og_image',
        '_ebq_twitter_title'       => 'ebq_twitter_title',
        '_ebq_twitter_description' => 'ebq_twitter_description',
        '_ebq_twitter_image'       => 'ebq_twitter_image',
        '_ebq_twitter_card'        => 'ebq_twitter_card',
        '_ebq_schema_type'         => 'ebq_schema_type',
        '_ebq_schema_disabled'     => 'ebq_schema_disabled',
        '_ebq_schemas'             => 'ebq_schemas',
        '_ebq_breadcrumbs'         => 'ebq_breadcrumbs',
    ];

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_meta_box(string $post_type): void
    {
        $supported = apply_filters('ebq_seo_fields_post_types', ['post', 'page']);
        if (! in_array($post_type, $supported, true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
            return;
        }

        add_meta_box(
            'ebq-seo-fields',
            __('EBQ SEO', 'ebq-seo'),
            [$this, 'render'],
            $post_type,
            'normal',
            'high'
        );
    }

    public function enqueue(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen) {
            return;
        }
        // Only mount on classic-editor screens — block editor has its own bundle.
        if (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
            return;
        }

        $bundle = EBQ_SEO_PATH.'build/classic-editor.js';
        if (! file_exists($bundle)) {
            return;
        }

        $asset_file = EBQ_SEO_PATH.'build/classic-editor.asset.php';
        $deps = ['wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-dom-ready'];
        $version = (string) filemtime($bundle);
        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            $deps = $asset['dependencies'] ?? $deps;
            $version = $asset['version'] ?? $version;
        }

        wp_enqueue_script('ebq-seo-classic', EBQ_SEO_URL.'build/classic-editor.js', $deps, $version, true);
        wp_set_script_translations('ebq-seo-classic', 'ebq-seo');

        // CRITICAL: this flag must be set BEFORE the bundle evaluates so the
        // editor-context dispatcher picks the DOM adapter on first import,
        // not the @wordpress/data adapter (which would throw on classic
        // screens since `core/editor` isn't registered there).
        wp_add_inline_script('ebq-seo-classic', 'window.__EBQ_CLASSIC__ = true;', 'before');

        // Guarantee apiFetch's REST nonce + root URL are available. WP only
        // auto-localizes wpApiSettings on certain admin screens — classic
        // post.php with the Classic Editor plugin is sometimes left out, so
        // the "+ Track" button (which calls /wp-json/ebq/v1/track-keyword)
        // would 401. Setting it ourselves makes the call work everywhere.
        $this->ensure_api_fetch_settings('ebq-seo-classic');

        // Sidebar CSS — same one the block editor uses.
        $css = EBQ_SEO_PATH.'build/sidebar.css';
        if (file_exists($css)) {
            wp_enqueue_style('ebq-seo-classic', EBQ_SEO_URL.'build/sidebar.css', [], (string) filemtime($css));
        }

        wp_localize_script('ebq-seo-classic', 'ebqSeoPublic', [
            'appBase'  => EBQ_Api_Client::base_url(),
            'homeUrl'  => home_url('/'),
            'siteName' => get_bloginfo('name'),
            'titleSep' => EBQ_Title_Template::get_sep(),
            'isConnected' => EBQ_Plugin::is_configured(),
            'settingsUrl' => admin_url('admin.php?page=ebq-seo'),
            'workspaceDomain' => (string) get_option('ebq_website_domain', ''),
            'tier' => (string) (get_option('ebq_site_tier', 'free') ?: 'free'),
        ]);
    }

    /**
     * Localize a fresh wpApiSettings { root, nonce } if WP hasn't already
     * done it. Idempotent — uses `window.wpApiSettings ||= …` so we never
     * stomp a value core has already set. Inline before the handle so the
     * apiFetch nonce middleware reads the right value on first call.
     */
    private function ensure_api_fetch_settings(string $handle): void
    {
        $payload = wp_json_encode([
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'versionString' => 'wp/v2/',
        ]);
        wp_add_inline_script(
            $handle,
            'window.wpApiSettings = window.wpApiSettings || ' . $payload . ';',
            'before'
        );
    }

    public function render(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        // Seed meta values for the JS classic store.
        $meta_seed = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $meta_seed[$key] = EBQ_Meta_Fields::get($post->ID, $key, '');
        }

        // The JS bundle reads window.ebqClassicMeta on first render.
        printf(
            '<script>window.ebqClassicMeta = %s;</script>',
            wp_json_encode($meta_seed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // React mount point.
        echo '<div id="ebq-classic-root" class="ebq-classic-mount"></div>';

        // No-JS fallback message.
        echo '<noscript><p style="font-size:12px;color:#64748b;padding:12px;border:1px solid #e2e8f0;border-radius:6px;">';
        esc_html_e('EBQ SEO needs JavaScript to render the editor panel. Enable JS or use the block editor.', 'ebq-seo');
        echo '</p></noscript>';

        // Hidden form inputs — the React store updates these on every change,
        // and the form-POST save handler reads them. Pre-rendered with current
        // values so a no-JS save is still safe.
        echo '<div style="display:none" aria-hidden="true">';
        foreach (self::FIELDS as $meta_key => $field_name) {
            $value = EBQ_Meta_Fields::get($post->ID, $meta_key, '');
            $is_textarea = in_array($meta_key, ['_ebq_description', '_ebq_og_description', '_ebq_twitter_description', '_ebq_schemas', '_ebq_breadcrumbs'], true);
            $is_checkbox_like = in_array($meta_key, ['_ebq_robots_noindex', '_ebq_robots_nofollow', '_ebq_schema_disabled'], true);
            if ($is_checkbox_like) {
                $cast = $value ? '1' : '';
                printf('<input type="hidden" name="%s" value="%s" />', esc_attr($field_name), esc_attr($cast));
            } elseif ($is_textarea) {
                printf('<textarea name="%s">%s</textarea>', esc_attr($field_name), esc_textarea((string) $value));
            } else {
                printf('<input type="hidden" name="%s" value="%s" />', esc_attr($field_name), esc_attr((string) $value));
            }
        }
        echo '</div>';
    }

    public function save(int $post_id, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }
        if (! isset($_POST[self::NONCE_FIELD]) || ! wp_verify_nonce((string) $_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }

        $simple = [
            '_ebq_title' => 'ebq_title',
            '_ebq_description' => 'ebq_description',
            '_ebq_focus_keyword' => 'ebq_focus_keyword',
            '_ebq_og_title' => 'ebq_og_title',
            '_ebq_og_description' => 'ebq_og_description',
            '_ebq_twitter_title' => 'ebq_twitter_title',
            '_ebq_twitter_description' => 'ebq_twitter_description',
            '_ebq_twitter_card' => 'ebq_twitter_card',
            '_ebq_schema_type' => 'ebq_schema_type',
        ];
        $urls = [
            '_ebq_canonical' => 'ebq_canonical',
            '_ebq_og_image' => 'ebq_og_image',
            '_ebq_twitter_image' => 'ebq_twitter_image',
        ];
        $booleans = [
            '_ebq_robots_noindex' => 'ebq_robots_noindex',
            '_ebq_robots_nofollow' => 'ebq_robots_nofollow',
            '_ebq_schema_disabled' => 'ebq_schema_disabled',
        ];

        foreach ($simple as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? sanitize_text_field((string) wp_unslash($_POST[$post_key])) : '';
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        foreach ($urls as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? esc_url_raw((string) wp_unslash($_POST[$post_key])) : '';
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        foreach ($booleans as $meta_key => $post_key) {
            $value = ! empty($_POST[$post_key]);
            update_post_meta($post_id, $meta_key, $value ? '1' : '');
        }

        $adv = isset($_POST['ebq_robots_advanced']) ? sanitize_text_field((string) wp_unslash($_POST['ebq_robots_advanced'])) : '';
        if ($adv === '') {
            delete_post_meta($post_id, '_ebq_robots_advanced');
        } else {
            update_post_meta($post_id, '_ebq_robots_advanced', mb_substr($adv, 0, 200));
        }

        // Schemas — JSON blob from a hidden textarea, normalized through the
        // shared sanitizer so the same shape contract holds for both editors.
        if (isset($_POST['ebq_schemas'])) {
            $raw_schemas = (string) wp_unslash($_POST['ebq_schemas']);
            $clean = EBQ_Meta_Fields::sanitize_schemas($raw_schemas);
            if ($clean === '') {
                delete_post_meta($post_id, '_ebq_schemas');
            } else {
                update_post_meta($post_id, '_ebq_schemas', $clean);
            }
        }

        // Breadcrumb override — same JSON pattern.
        if (isset($_POST['ebq_breadcrumbs'])) {
            $raw_b = (string) wp_unslash($_POST['ebq_breadcrumbs']);
            $clean_b = EBQ_Meta_Fields::sanitize_breadcrumbs($raw_b);
            if ($clean_b === '') {
                delete_post_meta($post_id, '_ebq_breadcrumbs');
            } else {
                update_post_meta($post_id, '_ebq_breadcrumbs', $clean_b);
            }
        }

        unset($post);
    }
}
