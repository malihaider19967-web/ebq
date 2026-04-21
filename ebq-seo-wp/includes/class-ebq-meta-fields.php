<?php
/**
 * Registers the per-post SEO meta keys that power v2's Yoast-replacement
 * surface: title, description, canonical, robots, focus keyword, and the
 * Open Graph / Twitter overrides.
 *
 * Every field is registered with `show_in_rest` so the Gutenberg editor can
 * read/write them, and with a sanitize callback so bad input is cleaned at
 * the database boundary.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Meta_Fields
{
    public const KEYS = [
        // Core SEO (P1)
        '_ebq_title' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_description' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_canonical' => ['type' => 'string', 'sanitize' => 'esc_url_raw'],
        '_ebq_robots_noindex' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean'],
        '_ebq_robots_nofollow' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean'],
        '_ebq_robots_advanced' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_focus_keyword' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],

        // Social (P2)
        '_ebq_og_title' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_og_description' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_og_image' => ['type' => 'string', 'sanitize' => 'esc_url_raw'],
        '_ebq_twitter_title' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_twitter_description' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
        '_ebq_twitter_image' => ['type' => 'string', 'sanitize' => 'esc_url_raw'],
        '_ebq_twitter_card' => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],

        // Schema override (P3)
        '_ebq_schema_type' => ['type' => 'string', 'sanitize' => 'sanitize_key'],
        '_ebq_schema_disabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean'],
    ];

    public function register(): void
    {
        add_action('init', [$this, 'register_meta']);
    }

    public function register_meta(): void
    {
        $object_subtypes = array_merge(['post', 'page'], (array) get_post_types(['public' => true, '_builtin' => false], 'names'));
        $object_subtypes = array_values(array_unique($object_subtypes));

        foreach (self::KEYS as $key => $def) {
            $args = [
                'type' => $def['type'],
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [$this, 'current_user_can_edit'],
                'sanitize_callback' => $def['sanitize'],
            ];
            // Boolean fields need the richer REST schema shape.
            if ($def['type'] === 'boolean') {
                $args['default'] = false;
            } else {
                $args['default'] = '';
            }
            foreach ($object_subtypes as $subtype) {
                register_post_meta($subtype, $key, $args);
            }
        }
    }

    public function current_user_can_edit(bool $allowed, string $meta_key, int $object_id): bool
    {
        unset($meta_key);

        return current_user_can('edit_post', $object_id);
    }

    /**
     * Convenience accessor the output classes use when composing `<head>` tags.
     */
    public static function get(int $post_id, string $key, $default = '')
    {
        $value = get_post_meta($post_id, $key, true);
        if ($value === '' || $value === null || $value === false) {
            return $default;
        }

        return $value;
    }
}
