<?php
/**
 * Yoast SEO → EBQ migration source.
 *
 *   - Per-post meta (title / description / focus / canonical / robots /
 *     social) → matching `_ebq_*` keys.
 *   - Yoast Premium `_yoast_wpseo_focuskeywords` (JSON of
 *     `[{keyword,score},...]`) → `_ebq_additional_keywords` (string list).
 *   - Yoast `_yoast_wpseo_schema_article_type` → an EBQ Article schema
 *     entry with the matching subtype (Article/BlogPosting/NewsArticle).
 *   - `yoast/faq-block` Gutenberg blocks parsed out of post_content →
 *     EBQ FAQ schema entries (one per block, merged with auto-detected
 *     server-side blocks the existing schema-output already handles).
 *
 * Yoast has no per-post breadcrumb override; that path is unused here.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Migration_Yoast extends EBQ_Migration_Source
{
    /** Direct copy: Yoast meta key → EBQ meta key, no value transform. */
    private const DIRECT_COPY = [
        '_yoast_wpseo_title'                 => '_ebq_title',
        '_yoast_wpseo_metadesc'              => '_ebq_description',
        '_yoast_wpseo_focuskw'               => '_ebq_focus_keyword',
        '_yoast_wpseo_canonical'             => '_ebq_canonical',
        '_yoast_wpseo_meta-robots-adv'       => '_ebq_robots_advanced',
        '_yoast_wpseo_opengraph-title'       => '_ebq_og_title',
        '_yoast_wpseo_opengraph-description' => '_ebq_og_description',
        '_yoast_wpseo_opengraph-image'       => '_ebq_og_image',
        '_yoast_wpseo_twitter-title'         => '_ebq_twitter_title',
        '_yoast_wpseo_twitter-description'   => '_ebq_twitter_description',
        '_yoast_wpseo_twitter-image'         => '_ebq_twitter_image',
    ];

    public function id(): string { return 'yoast'; }
    public function label(): string { return 'Yoast SEO'; }

    /**
     * Yoast Premium's redirects live in two site options:
     *   `wpseo-premium-redirects-base`        (plain rules)
     *   `wpseo-premium-redirects-base_regex`  (regex rules)
     * Each is a map of source_path → { url, type }. Counts both for
     * the preview's "site-level data" line.
     */
    public function site_level_counts(): array
    {
        $plain = (array) get_option('wpseo-premium-redirects-base', []);
        $regex = (array) get_option('wpseo-premium-redirects-base_regex', []);
        return ['redirects' => count($plain) + count($regex)];
    }

    public function is_available(): bool
    {
        if (defined('WPSEO_VERSION')) return true;
        return $this->count_posts() > 0;
    }

    public function count_posts(): int
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key LIKE %s
               AND p.post_status IN ('publish','draft','future','pending','private')",
            $wpdb->esc_like('_yoast_wpseo_') . '%'
        );
        return (int) $wpdb->get_var($sql);
    }

    public function post_ids(int $offset, int $limit): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key LIKE %s
               AND p.post_status IN ('publish','draft','future','pending','private')
             ORDER BY pm.post_id ASC
             LIMIT %d OFFSET %d",
            $wpdb->esc_like('_yoast_wpseo_') . '%',
            $limit,
            $offset
        );
        $rows = (array) $wpdb->get_col($sql);
        return array_map('intval', $rows);
    }

    public function migrate_post(WP_Post $post): array
    {
        $imported = [];
        $errors = [];
        foreach ($this->gather_changes($post) as $change) {
            if ($change['will_skip']) continue;
            // Schemas need a direct write because write_if_empty's
            // "key already set" check matches on raw scalar emptiness;
            // the schemas guard already happened during gather.
            if ($change['key'] === '_ebq_schemas') {
                update_post_meta($post->ID, '_ebq_schemas', $change['raw']);
                $imported[] = '_ebq_schemas';
                continue;
            }
            if (EBQ_Migration::write_if_empty($post->ID, $change['key'], $change['raw'])) {
                $imported[] = $change['key'];
            }
        }
        return ['imported_keys' => $imported, 'errors' => $errors];
    }

    public function preview_post(WP_Post $post): array
    {
        $changes = array_map(
            // Don't leak the raw `raw` value to the UI — strip it.
            static fn ($c) => array_diff_key($c, ['raw' => true]),
            $this->gather_changes($post)
        );
        return [
            'post_id'    => $post->ID,
            'post_title' => $post->post_title !== '' ? $post->post_title : sprintf('(no title — #%d)', $post->ID),
            'post_type'  => $post->post_type,
            'edit_url'   => get_edit_post_link($post->ID, 'raw'),
            'changes'    => $changes,
        ];
    }

    /**
     * Single source of truth for both migrate + preview.
     *
     * @return list<array{key:string, label:string, summary:string, will_skip:bool, raw:mixed}>
     */
    private function gather_changes(WP_Post $post): array
    {
        $id = $post->ID;
        $out = [];

        // 1. Direct-copy meta.
        $labels = $this->ebq_field_labels();
        foreach (self::DIRECT_COPY as $yoast_key => $ebq_key) {
            $value = (string) get_post_meta($id, $yoast_key, true);
            if ($value === '') continue;
            $out[] = [
                'key'       => $ebq_key,
                'label'     => $labels[$ebq_key] ?? $ebq_key,
                'summary'   => EBQ_Migration::summarize($value),
                'will_skip' => EBQ_Migration::ebq_key_set($id, $ebq_key),
                'raw'       => $value,
            ];
        }

        // 2. Robots flags — only emit a change when Yoast's value
        //    actively turns the flag on (skip Yoast's "site default").
        $noindex = (string) get_post_meta($id, '_yoast_wpseo_meta-robots-noindex', true);
        if ($noindex === '2') {
            $out[] = [
                'key' => '_ebq_robots_noindex',
                'label' => $labels['_ebq_robots_noindex'],
                'summary' => 'on',
                'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_robots_noindex'),
                'raw' => true,
            ];
        }
        $nofollow = (string) get_post_meta($id, '_yoast_wpseo_meta-robots-nofollow', true);
        if ($nofollow === '1') {
            $out[] = [
                'key' => '_ebq_robots_nofollow',
                'label' => $labels['_ebq_robots_nofollow'],
                'summary' => 'on',
                'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_robots_nofollow'),
                'raw' => true,
            ];
        }

        // 3. Premium additional focus keywords.
        $extras_raw = (string) get_post_meta($id, '_yoast_wpseo_focuskeywords', true);
        if ($extras_raw !== '') {
            $decoded = json_decode($extras_raw, true);
            if (is_array($decoded)) {
                $words = [];
                foreach ($decoded as $row) {
                    if (is_array($row) && ! empty($row['keyword'])) {
                        $words[] = (string) $row['keyword'];
                    }
                }
                if (! empty($words)) {
                    $clean = EBQ_Meta_Fields::sanitize_additional_keywords(wp_json_encode($words));
                    if ($clean !== '') {
                        $out[] = [
                            'key' => '_ebq_additional_keywords',
                            'label' => $labels['_ebq_additional_keywords'],
                            'summary' => sprintf('%d keyphrase(s): %s', count($words), implode(', ', array_slice($words, 0, 3)) . (count($words) > 3 ? '…' : '')),
                            'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_additional_keywords'),
                            'raw' => $clean,
                        ];
                    }
                }
            }
        }

        // 4. Schemas.
        $schemas = $this->build_schema_entries($post);
        if (! empty($schemas)) {
            $clean = EBQ_Meta_Fields::sanitize_schemas(wp_json_encode($schemas));
            if ($clean !== '') {
                $types = array_map(static fn ($s) => (string) ($s['type'] ?? '?'), $schemas);
                $out[] = [
                    'key' => '_ebq_schemas',
                    'label' => $labels['_ebq_schemas'],
                    'summary' => implode(', ', $types),
                    'will_skip' => (string) get_post_meta($id, '_ebq_schemas', true) !== '',
                    'raw' => $clean,
                ];
            }
        }
        return $out;
    }

    /**
     * Friendly labels for the preview UI. Centralised so the table
     * column reads as English, not as raw `_ebq_*` meta keys.
     *
     * @return array<string, string>
     */
    private function ebq_field_labels(): array
    {
        return [
            '_ebq_title'                => __('SEO title', 'ebq-seo'),
            '_ebq_description'          => __('Meta description', 'ebq-seo'),
            '_ebq_focus_keyword'        => __('Focus keyphrase', 'ebq-seo'),
            '_ebq_canonical'            => __('Canonical URL', 'ebq-seo'),
            '_ebq_robots_noindex'       => __('Noindex', 'ebq-seo'),
            '_ebq_robots_nofollow'      => __('Nofollow', 'ebq-seo'),
            '_ebq_robots_advanced'      => __('Advanced robots', 'ebq-seo'),
            '_ebq_og_title'             => __('OG title', 'ebq-seo'),
            '_ebq_og_description'       => __('OG description', 'ebq-seo'),
            '_ebq_og_image'             => __('OG image', 'ebq-seo'),
            '_ebq_twitter_title'        => __('Twitter title', 'ebq-seo'),
            '_ebq_twitter_description'  => __('Twitter description', 'ebq-seo'),
            '_ebq_twitter_image'        => __('Twitter image', 'ebq-seo'),
            '_ebq_twitter_card'         => __('Twitter card', 'ebq-seo'),
            '_ebq_additional_keywords'  => __('Additional keyphrases', 'ebq-seo'),
            '_ebq_schemas'              => __('Schemas (JSON-LD)', 'ebq-seo'),
            '_ebq_breadcrumbs'          => __('Breadcrumb override', 'ebq-seo'),
        ];
    }

    /**
     * Build EBQ schema entries from Yoast's mixed sources:
     *   (a) `_yoast_wpseo_schema_article_type` post meta — Article subtype
     *   (b) `_yoast_wpseo_schema_page_type` post meta — WebPage subtype
     *        (FAQPage, AboutPage, ContactPage, etc.)
     *   (c) `yoast/faq-block` blocks in post_content
     *   (d) `yoast/how-to-block` blocks → Custom schema entry (EBQ has
     *        no dedicated HowTo template UI yet, so we preserve the data
     *        in the Custom builder rather than dropping it)
     *
     * Posts where Yoast emits its DEFAULT auto schema (Article + WebPage
     * + Organization, no per-post config) get nothing here — those are
     * unchanged on the EBQ side because EBQ auto-emits the same default
     * graph, so nothing is lost.
     *
     * @return list<array<string, mixed>>
     */
    private function build_schema_entries(WP_Post $post): array
    {
        $entries = [];

        // (a) Article subtype.
        $article_type = (string) get_post_meta($post->ID, '_yoast_wpseo_schema_article_type', true);
        $valid_subtypes = ['Article', 'BlogPosting', 'NewsArticle'];
        if ($article_type !== '' && in_array($article_type, $valid_subtypes, true)) {
            $entries[] = [
                'id'       => wp_generate_uuid4(),
                'template' => 'article',
                'type'     => $article_type,
                'enabled'  => true,
                'data'     => [
                    'headline'      => '%title%',
                    'description'   => '%excerpt%',
                    'image'         => '%featured_image%',
                    'datePublished' => '%date%',
                    'dateModified'  => '%modified%',
                    'authorName'    => '%author%',
                ],
            ];
        }

        // (b) WebPage subtype — non-default values like FAQPage,
        //     AboutPage, ContactPage. Yoast also stores 'WebPage' as
        //     the explicit "default" — skip that since it's the same
        //     as auto.
        $page_type = (string) get_post_meta($post->ID, '_yoast_wpseo_schema_page_type', true);
        if ($page_type !== '' && $page_type !== 'WebPage') {
            $entries[] = [
                'id'       => wp_generate_uuid4(),
                'template' => 'custom',
                'type'     => $page_type,
                'enabled'  => true,
                'data'     => [
                    'properties' => [
                        ['name' => 'name', 'value' => '%title%'],
                        ['name' => 'description', 'value' => '%excerpt%'],
                        ['name' => 'url', 'value' => '%url%'],
                    ],
                ],
            ];
        }

        // (c) + (d) Block-driven schemas in post content.
        if (function_exists('parse_blocks') && $post->post_content !== '') {
            $blocks = parse_blocks($post->post_content);
            $faq_questions = $this->extract_faq_questions($blocks);
            if (! empty($faq_questions)) {
                $entries[] = [
                    'id'       => wp_generate_uuid4(),
                    'template' => 'faq',
                    'type'     => 'FAQPage',
                    'enabled'  => true,
                    'data'     => ['questions' => $faq_questions],
                ];
            }
            $howto_steps = $this->extract_howto_steps($blocks);
            if (! empty($howto_steps)) {
                // No EBQ HowTo template → preserve as Custom so the data
                // survives migration and the user can edit it.
                $entries[] = [
                    'id'       => wp_generate_uuid4(),
                    'template' => 'custom',
                    'type'     => 'HowTo',
                    'enabled'  => true,
                    'data'     => [
                        'properties' => [
                            ['name' => 'name', 'value' => '%title%'],
                            ['name' => 'step', 'value' => wp_json_encode($howto_steps)],
                        ],
                    ],
                ];
            }
        }

        return $entries;
    }

    /**
     * Recursively walk the block tree pulling `yoast/how-to-block` steps.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array{name?:string, text:string}>
     */
    private function extract_howto_steps(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $name = (string) ($block['blockName'] ?? '');
            if ($name === 'yoast/how-to-block') {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $steps = is_array($attrs['steps'] ?? null) ? $attrs['steps'] : [];
                foreach ($steps as $s) {
                    if (! is_array($s)) continue;
                    $step_name = trim((string) ($s['name'] ?? $s['title'] ?? ''));
                    $step_text = trim((string) ($s['text'] ?? $s['description'] ?? $s['content'] ?? ''));
                    if ($step_text === '' && $step_name === '') continue;
                    $entry = ['text' => $step_text !== '' ? $step_text : $step_name];
                    if ($step_name !== '' && $step_name !== $entry['text']) {
                        $entry['name'] = $step_name;
                    }
                    $out[] = $entry;
                }
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $out = array_merge($out, $this->extract_howto_steps($block['innerBlocks']));
            }
        }
        return $out;
    }

    /**
     * Recursively walk the block tree pulling `yoast/faq-block` items.
     * Each block stores `attrs.questions = [{question, answer}, ...]`.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array{question:string, answer:string}>
     */
    private function extract_faq_questions(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $name = (string) ($block['blockName'] ?? '');
            if ($name === 'yoast/faq-block') {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $questions = is_array($attrs['questions'] ?? null) ? $attrs['questions'] : [];
                foreach ($questions as $q) {
                    if (! is_array($q)) continue;
                    $question = trim((string) ($q['question'] ?? $q['title'] ?? ''));
                    $answer   = trim((string) ($q['answer']   ?? $q['content'] ?? ''));
                    if ($question === '' || $answer === '') continue;
                    $out[] = ['question' => $question, 'answer' => $answer];
                }
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $out = array_merge($out, $this->extract_faq_questions($block['innerBlocks']));
            }
        }
        return $out;
    }
}
