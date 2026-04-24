<?php
/**
 * Resolves Yoast-style title tokens: %%title%%, %%sep%%, %%sitename%%.
 *
 * Separator is configurable under Settings → EBQ SEO (option ebq_title_sep).
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Title_Template
{
    public const OPTION_SEP = 'ebq_title_sep';

    public static function default_sep(): string
    {
        return '–';
    }

    public static function get_sep(): string
    {
        $v = (string) get_option(self::OPTION_SEP, '');

        return $v !== '' ? $v : self::default_sep();
    }

    /**
     * Replace variables in a stored SEO title. Plain text (no tokens) is returned stripped.
     */
    public static function resolve(string $template, int $post_id): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        if (strpos($template, '%%') === false) {
            return wp_strip_all_tags($template);
        }

        $title = (string) get_the_title($post_id);
        if ($title === '') {
            $title = (string) get_bloginfo('name');
        }

        $replacements = [
            '%%title%%' => $title,
            '%%sep%%' => self::get_sep(),
            '%%sitename%%' => (string) get_bloginfo('name'),
            '%%page%%' => self::page_suffix($post_id),
        ];

        $out = str_replace(array_keys($replacements), array_values($replacements), $template);
        $out = preg_replace('/\s+/', ' ', trim($out));

        return wp_strip_all_tags((string) $out);
    }

    private static function page_suffix(int $post_id): string
    {
        global $pages;
        $numpages = 0;
        if (is_array($pages)) {
            $numpages = count($pages);
        }
        if ($numpages <= 1) {
            return '';
        }
        $paged = get_query_var('page') ? (int) get_query_var('page') : 1;
        if ($paged < 2) {
            return '';
        }

        return sprintf(
            /* translators: %d: current page number */
            __('(page %d)', 'ebq-seo'),
            $paged
        );
    }
}
