<?php
/**
 * Persists lightweight derived metrics on save (indexables-lite).
 *
 * @see docs/plugin-roadmap.md
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Analysis_Cache
{
    public const META_KEY = '_ebq_analysis_cache';

    public function register(): void
    {
        add_action('save_post', [$this, 'maybe_refresh'], 25, 2);
    }

    public function maybe_refresh(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (! in_array($post->post_status, ['publish', 'draft', 'pending', 'future', 'private'], true)) {
            return;
        }
        if (! is_post_type_viewable($post->post_type)) {
            return;
        }

        $content = (string) $post->post_content;
        $hash = md5($content."\n".$post->post_title);

        $plain = trim(wp_strip_all_tags($content));
        $words = $plain === '' ? 0 : count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $headings = ['h2' => 0, 'h3' => 0];
        if ($content !== '' && function_exists('parse_blocks')) {
            $this->count_headings(parse_blocks($content), $headings);
        }

        $payload = [
            'content_hash' => $hash,
            'word_count' => $words,
            'h2' => $headings['h2'],
            'h3' => $headings['h3'],
            'updated_at' => gmdate('c'),
        ];

        update_post_meta($post_id, self::META_KEY, wp_json_encode($payload));
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array{h2: int, h3: int}  $headings
     */
    private function count_headings(array $blocks, array &$headings): void
    {
        foreach ($blocks as $block) {
            $name = (string) ($block['blockName'] ?? '');
            if ($name === 'core/heading') {
                $level = isset($block['attrs']['level']) ? (int) $block['attrs']['level'] : 2;
                if ($level === 2) {
                    $headings['h2']++;
                } elseif ($level === 3) {
                    $headings['h3']++;
                }
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $this->count_headings($block['innerBlocks'], $headings);
            }
        }
    }
}
