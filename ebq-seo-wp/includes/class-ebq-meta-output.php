<?php
/**
 * Emits the core SEO tags in <head>: title, meta description, canonical,
 * robots directives.
 *
 * Coexistence guard: if another SEO plugin (Yoast, Rank Math, AIOSEO, SEO
 * Framework) is active, EBQ stands down for the overlapping tags so a site
 * doesn't end up with duplicate `<title>`, meta, or canonical elements. This
 * lets users install EBQ alongside an incumbent, import their data, then
 * deactivate the incumbent at a time of their choosing.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Meta_Output
{
    public function register(): void
    {
        if (self::another_seo_plugin_is_active()) {
            return;
        }

        // Title: take over wp_title via the modern document_title_parts filter.
        add_filter('pre_get_document_title', [$this, 'filter_title'], 15);
        add_filter('document_title_parts', [$this, 'filter_title_parts'], 15);

        // Theme support for title-tag is required — most modern themes declare it.
        add_theme_support('title-tag');

        // Everything else goes via wp_head.
        add_action('wp_head', [$this, 'output_meta'], 1);

        // Built-in generator+feed robots tags conflict; let WP handle them.
    }

    public static function another_seo_plugin_is_active(): bool
    {
        return defined('WPSEO_VERSION')              // Yoast
            || defined('RANK_MATH_VERSION')          // Rank Math
            || defined('AIOSEO_VERSION')             // AIOSEO
            || class_exists('The_SEO_Framework\\Load');
    }

    public function filter_title(string $title): string
    {
        if (! is_singular()) {
            return $title;
        }
        $post_id = (int) get_queried_object_id();
        $custom = EBQ_Meta_Fields::get($post_id, '_ebq_title', '');
        if ($custom !== '') {
            return EBQ_Title_Template::resolve((string) $custom, $post_id);
        }

        return $title;
    }

    public function filter_title_parts(array $parts): array
    {
        if (! is_singular()) {
            return $parts;
        }
        $post_id = (int) get_queried_object_id();
        $custom = EBQ_Meta_Fields::get($post_id, '_ebq_title', '');
        if ($custom !== '') {
            $parts['title'] = EBQ_Title_Template::resolve((string) $custom, $post_id);
        }

        return $parts;
    }

    public function output_meta(): void
    {
        $post_id = is_singular() ? (int) get_queried_object_id() : 0;

        // Meta description.
        $description = $this->resolve_description($post_id);
        if ($description !== '') {
            printf(
                "<meta name=\"description\" content=\"%s\" />\n",
                esc_attr($description)
            );
        }

        // Robots directives.
        $robots = $this->resolve_robots($post_id);
        if ($robots !== '') {
            printf("<meta name=\"robots\" content=\"%s\" />\n", esc_attr($robots));
        }

        // Canonical.
        if ($post_id > 0) {
            $canonical = EBQ_Meta_Fields::get($post_id, '_ebq_canonical', '');
            if ($canonical === '') {
                $canonical = (string) get_permalink($post_id);
            }
            if ($canonical !== '') {
                // Remove WP's own canonical link so we don't double up.
                remove_action('wp_head', 'rel_canonical');
                printf("<link rel=\"canonical\" href=\"%s\" />\n", esc_url($canonical));
            }
        }

        echo '<meta name="generator" content="EBQ SEO '.esc_attr(defined('EBQ_SEO_VERSION') ? EBQ_SEO_VERSION : '').'" />'."\n";
    }

    private function resolve_description(int $post_id): string
    {
        if ($post_id > 0) {
            $d = EBQ_Meta_Fields::get($post_id, '_ebq_description', '');
            if ($d !== '') {
                return $this->shorten($d);
            }
            $excerpt = trim((string) get_post_field('post_excerpt', $post_id));
            if ($excerpt !== '') {
                return $this->shorten($excerpt);
            }
            $content = trim(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));
            if ($content !== '') {
                return $this->shorten($content);
            }
        }
        $tagline = trim((string) get_bloginfo('description'));

        return $tagline !== '' ? $this->shorten($tagline) : '';
    }

    private function resolve_robots(int $post_id): string
    {
        $directives = [];

        $noindex = $post_id > 0 && EBQ_Meta_Fields::get($post_id, '_ebq_robots_noindex', false);
        $nofollow = $post_id > 0 && EBQ_Meta_Fields::get($post_id, '_ebq_robots_nofollow', false);

        $directives[] = $noindex ? 'noindex' : 'index';
        $directives[] = $nofollow ? 'nofollow' : 'follow';

        if ($post_id > 0) {
            $advanced = (string) EBQ_Meta_Fields::get($post_id, '_ebq_robots_advanced', '');
            if ($advanced !== '') {
                foreach (array_map('trim', explode(',', $advanced)) as $directive) {
                    if ($directive !== '') {
                        $directives[] = $directive;
                    }
                }
            }
        }

        return implode(', ', array_values(array_unique($directives)));
    }

    private function shorten(string $text, int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(function_exists('mb_substr') ? mb_substr($text, 0, $limit - 1) : substr($text, 0, $limit - 1)).'…';
    }
}
