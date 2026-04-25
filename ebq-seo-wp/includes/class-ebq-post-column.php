<?php
/**
 * Post-list "EBQ" column. Renders a skeleton on every row, then a single bulk
 * fetch (post-column-hydrate.js) hits /ebq/v1/bulk-post-insights and replaces
 * the skeleton with rank pill + flags + 30-day clicks/impressions.
 *
 * Keeps /wp-admin/edit.php snappy even with 100+ rows — only one network call.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Post_Column
{
    public function register(): void
    {
        $types = (array) apply_filters('ebq_post_column_post_types', ['post', 'page']);
        foreach ($types as $type) {
            add_filter("manage_{$type}_posts_columns", [$this, 'add_column']);
            add_action("manage_{$type}_posts_custom_column", [$this, 'render_column'], 10, 2);
        }
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // Quick-add row action: "Track focus keyphrase" — one-click promotes
        // the post's `_ebq_focus_keyword` into the Rank Tracker. Only renders
        // when the post actually has a focus keyword saved, otherwise the
        // user would land on an empty form.
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
        add_filter('page_row_actions', [$this, 'row_actions'], 10, 2);
    }

    /**
     * Inline row action: "+ Track focus keyphrase". One click adds the post's
     * focus keyword to Rank Tracker via the /ebq/v1/track-keyword REST proxy
     * — handled by post-column-hydrate.js, no page navigation. The link
     * carries the keyword as a data attribute and a small SVG accent so it
     * stands out from WP's default Edit / Quick Edit / Trash actions.
     */
    public function row_actions(array $actions, WP_Post $post): array
    {
        if (! current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        $keyword = (string) get_post_meta($post->ID, '_ebq_focus_keyword', true);
        if ($keyword === '') {
            return $actions;
        }
        // Fallback URL still navigates to HQ if JS doesn't load (no_js path).
        $fallback_url = add_query_arg([
            'page' => 'ebq-hq',
            'ebq_track' => rawurlencode($keyword),
            'ebq_track_url' => rawurlencode((string) get_permalink($post->ID)),
        ], admin_url('admin.php'));

        $actions['ebq_track'] = sprintf(
            '<a href="%s" class="ebq-row-track" data-ebq-keyword="%s" data-ebq-target-url="%s" data-ebq-post-id="%d" title="%s"><span class="ebq-row-track__plus" aria-hidden="true">+</span> %s</a>',
            esc_url($fallback_url),
            esc_attr($keyword),
            esc_attr((string) get_permalink($post->ID)),
            (int) $post->ID,
            esc_attr(sprintf(__('Add "%s" to Rank Tracker', 'ebq-seo'), $keyword)),
            esc_html__('Track keyphrase', 'ebq-seo')
        );
        return $actions;
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }
        if (! EBQ_Plugin::is_configured()) {
            return;
        }

        // Shared admin styles (rank pill, flags, skeleton shimmer).
        $css = EBQ_SEO_PATH.'build/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style('ebq-seo-admin', EBQ_SEO_URL.'build/admin.css', [], (string) filemtime($css));
        }

        $script = EBQ_SEO_PATH.'build/post-column-hydrate.js';
        if (! file_exists($script)) {
            return;
        }
        $asset_file = EBQ_SEO_PATH.'build/post-column-hydrate.asset.php';
        $deps = ['wp-api-fetch', 'wp-dom-ready', 'wp-i18n'];
        $version = (string) filemtime($script);
        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            $deps = $asset['dependencies'] ?? $deps;
            $version = $asset['version'] ?? $version;
        }
        wp_enqueue_script('ebq-post-column-hydrate', EBQ_SEO_URL.'build/post-column-hydrate.js', $deps, $version, true);
        wp_set_script_translations('ebq-post-column-hydrate', 'ebq-seo');

        // Some hosts / plugins (Classic Editor, certain caches) skip the WP
        // core localization of wpApiSettings on edit.php — without it, the
        // inline "+ Track keyphrase" row action's POST to /wp-json gets a
        // 401. Set it ourselves so it works everywhere.
        $payload = wp_json_encode([
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'versionString' => 'wp/v2/',
        ]);
        wp_add_inline_script(
            'ebq-post-column-hydrate',
            'window.wpApiSettings = window.wpApiSettings || ' . $payload . ';',
            'before'
        );
    }

    public function add_column(array $columns): array
    {
        $columns['ebq'] = __('EBQ', 'ebq-seo');

        return $columns;
    }

    public function render_column(string $column, int $post_id): void
    {
        if ($column !== 'ebq') {
            return;
        }

        // Local-only signals (no API call needed). All render synchronously
        // so the user sees something useful even before the EBQ API hydrate
        // (which fills rank/GSC) returns. Sites without an EBQ token still
        // get these.
        $seo_score   = $this->compute_seo_score($post_id);
        $read_score  = $this->compute_readability_score($post_id);
        $schemas     = $this->schema_types_for($post_id);
        $disabled    = (bool) get_post_meta($post_id, '_ebq_schema_disabled', true);

        echo '<div class="ebq-col-cell">';
        echo '<div class="ebq-col-scores">';
        $this->render_score_pill('seo', $seo_score, __('SEO', 'ebq-seo'));
        $this->render_score_pill('read', $read_score, __('Read.', 'ebq-seo'));
        echo '</div>';
        $this->render_schema_chips($schemas, $disabled);

        // EBQ API rank / GSC hydration block — separate skeleton so the
        // local-only signals above stay visible even if the API is slow
        // or the site isn't connected.
        if (EBQ_Plugin::is_configured()) {
            printf(
                '<div class="ebq-col-hydrate" data-ebq-col data-post="%d">'.
                    '<span class="ebq-col-skeleton">'.
                        '<span class="ebq-col-shimmer" style="width:60%%;"></span>'.
                        '<span class="ebq-col-shimmer" style="width:40%%;margin-top:4px;"></span>'.
                    '</span>'.
                '</div>',
                (int) $post_id
            );
        }
        echo '</div>';
    }

    /**
     * On-page SEO score (0–100). Heuristic that mirrors the major signals
     * the editor-side analyzer uses:
     *   - Focus keyphrase set         → 25
     *   - Title (SEO or post)         → 15  (well-formed length adds bonus)
     *   - Meta description present    → 15  (well-formed length adds bonus)
     *   - Focus keyword in title      → 25
     *   - Focus keyword in description → 20
     */
    private function compute_seo_score(int $post_id): int
    {
        $focus = trim((string) get_post_meta($post_id, '_ebq_focus_keyword', true));
        $title = trim((string) get_post_meta($post_id, '_ebq_title', true));
        if ($title === '') {
            $title = (string) get_the_title($post_id);
        }
        $desc  = trim((string) get_post_meta($post_id, '_ebq_description', true));

        $score = 0;
        if ($focus !== '') $score += 25;
        if ($title !== '') {
            $score += 10;
            $tlen = mb_strlen($title);
            if ($tlen >= 30 && $tlen <= 60) $score += 5;
        }
        if ($desc !== '') {
            $score += 10;
            $dlen = mb_strlen($desc);
            if ($dlen >= 130 && $dlen <= 160) $score += 5;
        }
        if ($focus !== '' && $title !== '' && stripos($title, $focus) !== false) $score += 25;
        if ($focus !== '' && $desc !== ''  && stripos($desc, $focus)  !== false) $score += 20;

        return max(0, min(100, $score));
    }

    /**
     * Distinct schema @type strings the post will emit, derived from the
     * `_ebq_schemas` JSON the Schema tab writes. Used for the badge row
     * shown in the EBQ post column.
     *
     * @return list<string>
     */
    private function schema_types_for(int $post_id): array
    {
        $raw = (string) get_post_meta($post_id, '_ebq_schemas', true);
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) return [];

        $types = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry) || empty($entry['enabled'])) continue;
            $type = (string) ($entry['type'] ?? '');
            if ($type === '') continue;
            // Dedupe by lowercased key but keep the original casing for display.
            $key = strtolower($type);
            if (! isset($types[$key])) {
                $types[$key] = $type;
            }
        }
        return array_values($types);
    }

    /**
     * Readability score (0–100) from a Flesch Reading Ease approximation.
     * Pure server-side, no external deps. Cheap to compute on edit.php
     * since we only need word/sentence/syllable counts on the post body.
     *
     * Buckets we use for the colored pill:
     *   Flesch ≥ 65 → green ("Good" — easy to read, plain English)
     *   Flesch 30–64 → orange ("Needs work" — fairly difficult)
     *   Flesch < 30 → red ("Bad" — very difficult)
     *
     * Empty posts score 0 (red) — match the SEO behaviour where missing
     * setup is explicitly flagged rather than silently neutral.
     */
    private function compute_readability_score(int $post_id): int
    {
        $content = (string) get_post_field('post_content', $post_id);
        $text = trim(wp_strip_all_tags(strip_shortcodes($content)));
        if ($text === '') return 0;

        // Sentence split on . ! ? followed by whitespace or end of string.
        $sentences = preg_split('/[.!?]+\s+|[.!?]+$/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = max(1, count($sentences));

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $word_count = max(1, count($words));

        // Syllable approximation: count vowel-group runs per word, with a
        // floor of 1 syllable. Good enough for English; degrades gracefully
        // for other languages by treating each word as 1.5 syllables avg.
        $syllables = 0;
        foreach ($words as $w) {
            $clean = preg_replace('/[^A-Za-z]/u', '', $w);
            if ($clean === '') {
                $syllables += 1;
                continue;
            }
            preg_match_all('/[aeiouy]+/i', $clean, $m);
            $count = count($m[0] ?? []);
            // Trailing silent 'e' usually doesn't add a syllable.
            if ($count > 1 && substr(strtolower($clean), -1) === 'e') $count--;
            $syllables += max(1, $count);
        }

        $flesch = 206.835 - 1.015 * ($word_count / $sentence_count) - 84.6 * ($syllables / $word_count);
        return (int) max(0, min(100, round($flesch)));
    }

    private function render_score_pill(string $kind, int $score, string $abbrev): void
    {
        $tone = $score >= 65 ? 'good' : ($score >= 45 ? 'warn' : 'bad');
        $label = $score >= 65 ? __('Good', 'ebq-seo') : ($score >= 45 ? __('Needs work', 'ebq-seo') : __('Bad', 'ebq-seo'));
        $title = $kind === 'seo'
            ? sprintf(__('On-page SEO score: %d / 100 — %s', 'ebq-seo'), $score, $label)
            : sprintf(__('Readability (Flesch) score: %d / 100 — %s', 'ebq-seo'), $score, $label);
        printf(
            '<span class="ebq-col-score ebq-col-score--%s" title="%s">'
                . '<span class="ebq-col-score__num">%d</span>'
                . '<span class="ebq-col-score__kind">%s</span>'
                . '<span class="ebq-col-score__label">%s</span>'
            . '</span>',
            esc_attr($tone),
            esc_attr($title),
            $score,
            esc_html($abbrev),
            esc_html($label)
        );
    }

    /**
     * Schema icon + chips. Three states:
     *   - disabled: red shield-with-slash icon + "Schema off"
     *   - auto:     neutral lightning icon + tooltip listing what auto emits
     *   - custom:   purple icon + chip per @type
     *
     * @param  list<string>  $types
     */
    private function render_schema_chips(array $types, bool $disabled): void
    {
        if ($disabled) {
            echo '<div class="ebq-col-schemas ebq-col-schemas--off" title="' . esc_attr__('Schema output is disabled for this post — no JSON-LD will be emitted on the front-end.', 'ebq-seo') . '">';
            echo $this->schema_icon('off');
            echo '<span class="ebq-col-schema-text">' . esc_html__('Schema off', 'ebq-seo') . '</span>';
            echo '</div>';
            return;
        }
        if (empty($types)) {
            echo '<div class="ebq-col-schemas ebq-col-schemas--auto" title="' . esc_attr__('Auto schema active. This post will emit Article + WebPage + Organization JSON-LD by default. Open the Schema tab to add custom types (Product, FAQ, Recipe, etc.).', 'ebq-seo') . '">';
            echo $this->schema_icon('auto');
            echo '<span class="ebq-col-schema-text">' . esc_html__('Auto', 'ebq-seo') . '</span>';
            echo '</div>';
            return;
        }
        $title = sprintf(
            /* translators: %s comma-separated schema type list */
            __('JSON-LD schemas this post emits: %s', 'ebq-seo'),
            implode(', ', $types)
        );
        echo '<div class="ebq-col-schemas" title="' . esc_attr($title) . '">';
        echo $this->schema_icon('custom');
        foreach ($types as $t) {
            printf('<span class="ebq-col-schema">%s</span>', esc_html($t));
        }
        echo '</div>';
    }

    private function schema_icon(string $variant): string
    {
        // Inline SVG so we don't ship an icon font / image.
        $svg_inner = match ($variant) {
            'off' => '<path d="M3 3l14 14M5 4.5L10 3l5 1.5v6c0 .9-.3 1.7-.8 2.4M5 4.5v6c0 3.5 2.5 6.5 5 7.5 1-.4 2-1 2.8-1.7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
            'auto' => '<path d="M11 2L4 11h4l-1 7 7-9h-4l1-7z" fill="currentColor"/>',
            default => '<path d="M5 3.5L10 2l5 1.5v6c0 3.5-2.5 6.5-5 7.5-2.5-1-5-4-5-7.5v-6z" fill="currentColor" opacity=".15"/><path d="M5 3.5L10 2l5 1.5v6c0 3.5-2.5 6.5-5 7.5-2.5-1-5-4-5-7.5v-6z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7.5 9.5L9.5 11.5L13 8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        };
        return '<span class="ebq-col-schema-icon ebq-col-schema-icon--' . esc_attr($variant) . '" aria-hidden="true">'
            . '<svg viewBox="0 0 20 20" width="14" height="14">' . $svg_inner . '</svg>'
            . '</span>';
    }
}
