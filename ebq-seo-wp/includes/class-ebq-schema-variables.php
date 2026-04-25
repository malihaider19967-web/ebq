<?php
/**
 * Resolves %var% placeholders inside user-entered schema field values at
 * JSON-LD emit time. Mirrors the surface RankMath/Yoast users expect:
 *
 *   %title%            post title
 *   %excerpt%          post excerpt (auto-generated if empty)
 *   %url%              post permalink
 *   %featured_image%   absolute URL of the featured image, or '' if none
 *   %author%           post author display name
 *   %date%             post date (ISO-8601)
 *   %modified%         post modified date (ISO-8601)
 *   %sitename%         get_bloginfo('name')
 *   %post_meta(key)%   any post meta value (escaped via wp_strip_all_tags)
 *
 * Only string values get resolved. Arrays are walked recursively so repeater
 * fields and nested data carry the same templating.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Schema_Variables
{
    /**
     * @param  mixed  $value
     * @return mixed
     */
    public static function resolve($value, int $post_id)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::resolve($v, $post_id);
            }
            return $out;
        }
        if (! is_string($value) || $value === '' || strpos($value, '%') === false) {
            return $value;
        }

        $vars = self::base_vars($post_id);

        // %post_meta(key)% — single-pass with a callback so the inner key is
        // sanitized to allowed meta-key chars.
        $value = preg_replace_callback('/%post_meta\(([A-Za-z0-9_\-]+)\)%/', function (array $m) use ($post_id): string {
            $raw = (string) get_post_meta($post_id, $m[1], true);
            return wp_strip_all_tags($raw);
        }, $value);

        foreach ($vars as $token => $replacement) {
            if (strpos($value, $token) !== false) {
                $value = str_replace($token, (string) $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function base_vars(int $post_id): array
    {
        $title = $post_id > 0 ? (string) get_the_title($post_id) : '';
        $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');

        $excerpt = '';
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post) {
                $excerpt = $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 30);
            }
        }

        $featured = '';
        if ($post_id > 0 && has_post_thumbnail($post_id)) {
            $featured = (string) get_the_post_thumbnail_url($post_id, 'full');
        }

        $author = '';
        $date = '';
        $modified = '';
        if ($post_id > 0) {
            $author_id = (int) get_post_field('post_author', $post_id);
            if ($author_id > 0) {
                $author = (string) get_the_author_meta('display_name', $author_id);
            }
            $date = (string) get_the_date('c', $post_id);
            $modified = (string) get_the_modified_date('c', $post_id);
        }

        return [
            '%title%'          => $title,
            '%excerpt%'        => $excerpt,
            '%url%'            => $url,
            '%featured_image%' => $featured,
            '%author%'         => $author,
            '%date%'           => $date,
            '%modified%'       => $modified,
            '%sitename%'       => (string) get_bloginfo('name'),
        ];
    }
}
