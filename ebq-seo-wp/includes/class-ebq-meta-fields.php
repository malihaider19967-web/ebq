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
        // JSON-encoded list (max 5) of additional keyphrases the post should
        // also rank for. Stored as a string so it can ride the existing
        // register_post_meta string contract; React parses on read, stringifies
        // on write. Sanitized to a normalized JSON shape on save.
        '_ebq_additional_keywords' => ['type' => 'string', 'sanitize' => [self::class, 'sanitize_additional_keywords']],

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
        // JSON-encoded list of user-configured schemas. Each item:
        //   { id, template, type, data: {...field values...}, enabled }
        // Sanitized through sanitize_schemas() to keep shape + caps tight.
        '_ebq_schemas' => ['type' => 'string', 'sanitize' => [self::class, 'sanitize_schemas']],

        // Per-post breadcrumb override — JSON shape:
        //   { mode: 'auto' | 'custom', items: [{ name, url, hidden? }] }
        // When mode='auto' (or empty), the schema output emits the default
        // Home → ancestors → current trail. When mode='custom', the items
        // array fully replaces it.
        '_ebq_breadcrumbs' => ['type' => 'string', 'sanitize' => [self::class, 'sanitize_breadcrumbs']],
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

    /**
     * Normalize the additional-keywords JSON: unique, non-empty, trimmed
     * strings, each ≤120 chars. Returns a JSON string (or ''). Soft cap at
     * 200 entries keeps the payload sane in pathological cases (paste of a
     * giant CSV) without being a meaningful limit for real users.
     */
    public static function sanitize_additional_keywords($value): string
    {
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : (is_array($value) ? $value : null);
        if (! is_array($decoded)) {
            return '';
        }
        $out = [];
        foreach ($decoded as $kw) {
            if (! is_string($kw)) continue;
            $clean = trim(sanitize_text_field($kw));
            if ($clean === '') continue;
            $clean = mb_substr($clean, 0, 120);
            $key = mb_strtolower($clean);
            if (isset($out[$key])) continue;
            $out[$key] = $clean;
            if (count($out) >= 200) break;
        }

        return $out === [] ? '' : (string) wp_json_encode(array_values($out));
    }

    /**
     * Normalize the user-configured schemas JSON. Accepts either a JSON string
     * or a pre-decoded array. Returns a JSON string capped at 20 schemas, each
     * with a stable id, template id, type, enabled flag, and a string-keyed
     * data map. Field values are passed through and only normalized for shape;
     * the renderer is responsible for value-level sanitization on output.
     */
    public static function sanitize_schemas($value): string
    {
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : (is_array($value) ? $value : null);
        if (! is_array($decoded)) {
            return '';
        }

        $out = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry)) continue;
            $template = sanitize_key((string) ($entry['template'] ?? ''));
            $type = sanitize_text_field((string) ($entry['type'] ?? ''));
            if ($template === '' || $type === '') continue;

            $id = (string) ($entry['id'] ?? '');
            $id = preg_replace('/[^A-Za-z0-9_\-]/', '', $id);
            if ($id === '') {
                $id = wp_generate_uuid4();
            }

            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            $clean_data = [];
            foreach ($data as $k => $v) {
                if (! is_string($k) || $k === '') continue;
                $clean_key = preg_replace('/[^A-Za-z0-9_\-]/', '', $k);
                if ($clean_key === '') continue;
                $clean_data[$clean_key] = self::clean_data_value($v);
            }

            $out[] = [
                'id' => $id,
                'template' => $template,
                'type' => mb_substr($type, 0, 80),
                'enabled' => array_key_exists('enabled', $entry) ? (bool) $entry['enabled'] : true,
                'data' => $clean_data,
            ];

            if (count($out) >= 20) break;
        }

        return $out === [] ? '' : (string) wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Normalize the per-post breadcrumb override. Accepts JSON or array.
     * Returns a JSON string (or '') with shape:
     *   { mode: 'auto'|'custom', items: [{ name, url, hidden }] }
     * 'auto' (or empty) means "use the auto-generated trail" — we still
     * keep the items list around so a user can flip back to custom without
     * losing their work.
     */
    public static function sanitize_breadcrumbs($value): string
    {
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : (is_array($value) ? $value : null);
        if (! is_array($decoded)) {
            return '';
        }
        $mode = ($decoded['mode'] ?? 'auto') === 'custom' ? 'custom' : 'auto';
        $items = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];

        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $name = trim(sanitize_text_field((string) ($item['name'] ?? '')));
            $url  = (string) esc_url_raw((string) ($item['url'] ?? ''));
            if ($name === '' && $url === '') continue;
            $clean[] = [
                'name'   => mb_substr($name, 0, 200),
                'url'    => $url,
                'hidden' => ! empty($item['hidden']),
            ];
            if (count($clean) >= 30) break;
        }

        if ($mode === 'auto' && empty($clean)) {
            return '';
        }

        return (string) wp_json_encode([
            'mode'  => $mode,
            'items' => $clean,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Recursively normalize a stored value: scalars become trimmed strings (≤2KB),
     * arrays of scalars/arrays survive (≤50 items deep, ≤2 levels). Output
     * sanitization (esc_html, esc_url) happens at JSON-LD emit time.
     */
    private static function clean_data_value($value, int $depth = 0)
    {
        if ($depth > 2) {
            return null;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (count($out) >= 50) break;
                $cleanV = self::clean_data_value($v, $depth + 1);
                if ($cleanV === null) continue;
                if (is_int($k)) {
                    $out[] = $cleanV;
                } else {
                    $clean_key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $k);
                    if ($clean_key !== '') {
                        $out[$clean_key] = $cleanV;
                    }
                }
            }
            return $out;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        $str = is_string($value) ? $value : (string) $value;
        $str = wp_check_invalid_utf8($str);
        if ($str === '') return '';
        return mb_substr($str, 0, 2048);
    }
}
