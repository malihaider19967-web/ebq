<?php
/**
 * Rank Math → EBQ migration source.
 *
 * Differences from Yoast worth knowing about while reading this:
 *   - Focus keywords are stored comma-separated in ONE meta key
 *     (`rank_math_focus_keyword`). First token is primary, the rest
 *     become EBQ "additional keyphrases".
 *   - Robots directives are a serialized PHP array (e.g.
 *     `['noindex','nofollow','noarchive']`), not a yes/no flag pair.
 *   - Each schema is its own meta row keyed `rank_math_schema_<TypeName>`
 *     with a serialized array value containing the rich shape Rank Math
 *     uses internally (mainEntity, recipeIngredient, etc.).
 *   - Per-post breadcrumb override is a single string in
 *     `rank_math_breadcrumb_title` — we map it to a custom EBQ trail.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Migration_RankMath extends EBQ_Migration_Source
{
    /** Direct copy: Rank Math meta key → EBQ meta key. */
    private const DIRECT_COPY = [
        'rank_math_title'                 => '_ebq_title',
        'rank_math_description'           => '_ebq_description',
        'rank_math_canonical_url'         => '_ebq_canonical',
        'rank_math_facebook_title'        => '_ebq_og_title',
        'rank_math_facebook_description'  => '_ebq_og_description',
        'rank_math_facebook_image'        => '_ebq_og_image',
        'rank_math_twitter_title'         => '_ebq_twitter_title',
        'rank_math_twitter_description'   => '_ebq_twitter_description',
        'rank_math_twitter_image'         => '_ebq_twitter_image',
        'rank_math_twitter_card_type'     => '_ebq_twitter_card',
    ];

    /** Schema @type → EBQ template id. Anything missing falls through to 'custom'. */
    private const SCHEMA_TYPE_MAP = [
        'Article'             => 'article',
        'BlogPosting'         => 'article',
        'NewsArticle'         => 'article',
        'Product'             => 'product',
        'FAQPage'             => 'faq',
        'Recipe'              => 'recipe',
        'Event'               => 'event',
        'LocalBusiness'       => 'local_business',
        'Restaurant'          => 'local_business',
        'Store'               => 'local_business',
        'ProfessionalService' => 'local_business',
        'MedicalBusiness'     => 'local_business',
        'Book'                => 'book',
        'Course'              => 'course',
        'JobPosting'          => 'job_posting',
        'VideoObject'         => 'video',
        'SoftwareApplication' => 'software',
        'Service'             => 'service',
        'Person'              => 'person',
        'MusicAlbum'          => 'music_album',
        'Movie'               => 'movie',
        'Review'              => 'review',
    ];

    public function id(): string { return 'rankmath'; }
    public function label(): string { return 'Rank Math'; }

    /**
     * Rank Math redirects live in their own custom table
     * `{prefix}rank_math_redirections`. Returns 0 if the table doesn't
     * exist (Rank Math uninstalled and table dropped) so we don't
     * surface a misleading row.
     */
    public function site_level_counts(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rank_math_redirections';
        // Quick existence check — SHOW TABLES is cheap and cached.
        $exists = (string) $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );
        if ($exists === '') {
            return ['redirects' => 0];
        }
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'active'");
        return ['redirects' => $count];
    }

    public function is_available(): bool
    {
        if (defined('RANK_MATH_VERSION')) return true;
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
            $wpdb->esc_like('rank_math_') . '%'
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
            $wpdb->esc_like('rank_math_') . '%',
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
            // Schemas + breadcrumbs need a direct write — write_if_empty's
            // "key already set" gate already happened in gather.
            if (in_array($change['key'], ['_ebq_schemas', '_ebq_breadcrumbs'], true)) {
                update_post_meta($post->ID, $change['key'], $change['raw']);
                $imported[] = $change['key'];
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
        $labels = $this->ebq_field_labels();

        // 1. Direct-copy strings.
        foreach (self::DIRECT_COPY as $rm_key => $ebq_key) {
            $value = (string) get_post_meta($id, $rm_key, true);
            if ($value === '') continue;
            $out[] = [
                'key' => $ebq_key,
                'label' => $labels[$ebq_key] ?? $ebq_key,
                'summary' => EBQ_Migration::summarize($value),
                'will_skip' => EBQ_Migration::ebq_key_set($id, $ebq_key),
                'raw' => $value,
            ];
        }

        // 2. Focus + additional keyphrases.
        $focus_raw = (string) get_post_meta($id, 'rank_math_focus_keyword', true);
        if ($focus_raw !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $focus_raw)), 'strlen'));
            if (! empty($parts)) {
                $primary = array_shift($parts);
                $out[] = [
                    'key' => '_ebq_focus_keyword',
                    'label' => $labels['_ebq_focus_keyword'],
                    'summary' => EBQ_Migration::summarize($primary),
                    'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_focus_keyword'),
                    'raw' => $primary,
                ];
                if (! empty($parts)) {
                    $clean = EBQ_Meta_Fields::sanitize_additional_keywords(wp_json_encode($parts));
                    if ($clean !== '') {
                        $out[] = [
                            'key' => '_ebq_additional_keywords',
                            'label' => $labels['_ebq_additional_keywords'],
                            'summary' => sprintf('%d keyphrase(s): %s', count($parts), implode(', ', array_slice($parts, 0, 3)) . (count($parts) > 3 ? '…' : '')),
                            'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_additional_keywords'),
                            'raw' => $clean,
                        ];
                    }
                }
            }
        }

        // 3. Robots flags.
        $robots = $this->maybe_unserialize_array(get_post_meta($id, 'rank_math_robots', true));
        if (! empty($robots)) {
            if (in_array('noindex', $robots, true)) {
                $out[] = [
                    'key' => '_ebq_robots_noindex',
                    'label' => $labels['_ebq_robots_noindex'],
                    'summary' => 'on',
                    'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_robots_noindex'),
                    'raw' => true,
                ];
            }
            if (in_array('nofollow', $robots, true)) {
                $out[] = [
                    'key' => '_ebq_robots_nofollow',
                    'label' => $labels['_ebq_robots_nofollow'],
                    'summary' => 'on',
                    'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_robots_nofollow'),
                    'raw' => true,
                ];
            }
            $advanced = array_values(array_intersect($robots, [
                'noarchive', 'nosnippet', 'noimageindex', 'notranslate', 'max-snippet:-1',
            ]));
            if (! empty($advanced)) {
                $value = implode(', ', $advanced);
                $out[] = [
                    'key' => '_ebq_robots_advanced',
                    'label' => $labels['_ebq_robots_advanced'],
                    'summary' => $value,
                    'will_skip' => EBQ_Migration::ebq_key_set($id, '_ebq_robots_advanced'),
                    'raw' => $value,
                ];
            }
        }

        // 4. Schemas.
        $entries = $this->build_schema_entries($id);
        if (! empty($entries)) {
            $clean = EBQ_Meta_Fields::sanitize_schemas(wp_json_encode($entries));
            if ($clean !== '') {
                $types = array_map(static fn ($s) => (string) ($s['type'] ?? '?'), $entries);
                $out[] = [
                    'key' => '_ebq_schemas',
                    'label' => $labels['_ebq_schemas'],
                    'summary' => implode(', ', $types),
                    'will_skip' => (string) get_post_meta($id, '_ebq_schemas', true) !== '',
                    'raw' => $clean,
                ];
            }
        }

        // 5. Breadcrumb title override.
        $bc_title = (string) get_post_meta($id, 'rank_math_breadcrumb_title', true);
        if ($bc_title !== '') {
            $payload = wp_json_encode([
                'mode'  => 'custom',
                'items' => [
                    ['name' => __('Home', 'ebq-seo'), 'url' => home_url('/'), 'hidden' => false],
                    ['name' => $bc_title,            'url' => '',              'hidden' => false],
                ],
            ]);
            $clean = EBQ_Meta_Fields::sanitize_breadcrumbs($payload);
            if ($clean !== '') {
                $out[] = [
                    'key' => '_ebq_breadcrumbs',
                    'label' => $labels['_ebq_breadcrumbs'],
                    'summary' => sprintf(__('custom trail (last item: "%s")', 'ebq-seo'), EBQ_Migration::summarize($bc_title, 40)),
                    'will_skip' => (string) get_post_meta($id, '_ebq_breadcrumbs', true) !== '',
                    'raw' => $clean,
                ];
            }
        }
        return $out;
    }

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
     * Pull schemas from THREE Rank Math storage formats and merge them
     * into one EBQ list:
     *
     *   1. `rank_math_schema_<TypeName>` — Pro/3.0+ format (one meta row
     *      per @type, value is a serialized array of fields).
     *   2. `rank_math_rich_snippet` (string slug like 'article', 'faq',
     *      'recipe') + `rank_math_snippet_*` flat field meta — the format
     *      Rank Math Free has used since v1.0. This is what most sites
     *      have.
     *   3. `rank-math/faq-block` and `rank-math/howto-block` Gutenberg
     *      blocks parsed out of post content.
     *
     * Dedupes by EBQ template id so a site that has both formats doesn't
     * end up with two FAQ schemas.
     *
     * @return list<array<string, mixed>>
     */
    private function build_schema_entries(int $post_id): array
    {
        $entries = [];
        $seen_templates = [];

        // ── Format 1: rank_math_schema_<TypeName> (Pro / newer)
        global $wpdb;
        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key LIKE %s",
                $post_id,
                $wpdb->esc_like('rank_math_schema_') . '%'
            ),
            ARRAY_A
        );
        foreach ($rows as $row) {
            $type = substr((string) $row['meta_key'], strlen('rank_math_schema_'));
            $data = $this->maybe_unserialize_array($row['meta_value']);
            if (empty($data) || $type === '') continue;

            $template_id = self::SCHEMA_TYPE_MAP[$type] ?? 'custom';
            $entry = [
                'id'       => wp_generate_uuid4(),
                'template' => $template_id,
                'type'     => $type,
                'enabled'  => true,
                'data'     => [],
            ];

            switch ($template_id) {
                case 'article':
                    $entry['data'] = [
                        'headline'      => (string) ($data['headline'] ?? ($data['name'] ?? '%title%')),
                        'description'   => (string) ($data['description'] ?? '%excerpt%'),
                        'image'         => (string) ($data['image']['url'] ?? ($data['image'] ?? '%featured_image%')),
                        'datePublished' => (string) ($data['datePublished'] ?? '%date%'),
                        'dateModified'  => (string) ($data['dateModified'] ?? '%modified%'),
                        'authorName'    => (string) ($data['author']['name'] ?? '%author%'),
                    ];
                    break;
                case 'product':
                    $entry['data'] = [
                        'name'        => (string) ($data['name'] ?? ''),
                        'description' => (string) ($data['description'] ?? ''),
                        'image'       => (string) ($data['image']['url'] ?? ($data['image'] ?? '')),
                        'sku'         => (string) ($data['sku'] ?? ''),
                        'brand'       => (string) ($data['brand']['name'] ?? ($data['brand'] ?? '')),
                        'price'       => (string) ($data['offers']['price'] ?? ($data['price'] ?? '')),
                        'currency'    => (string) ($data['offers']['priceCurrency'] ?? ($data['priceCurrency'] ?? '')),
                    ];
                    break;
                case 'faq':
                    $questions = [];
                    $main = $data['mainEntity'] ?? [];
                    if (is_array($main)) {
                        foreach ($main as $q) {
                            if (! is_array($q)) continue;
                            $name = (string) ($q['name'] ?? '');
                            $ans  = (string) ($q['acceptedAnswer']['text'] ?? '');
                            if ($name === '' || $ans === '') continue;
                            $questions[] = ['question' => $name, 'answer' => $ans];
                        }
                    }
                    $entry['data'] = ['questions' => $questions];
                    break;
                case 'recipe':
                    $entry['data'] = [
                        'name'           => (string) ($data['name'] ?? ''),
                        'description'    => (string) ($data['description'] ?? ''),
                        'image'          => (string) ($data['image']['url'] ?? ($data['image'] ?? '')),
                        'prepTime'       => (string) ($data['prepTime'] ?? ''),
                        'cookTime'       => (string) ($data['cookTime'] ?? ''),
                        'totalTime'      => (string) ($data['totalTime'] ?? ''),
                        'recipeYield'    => (string) ($data['recipeYield'] ?? ''),
                        'recipeCategory' => (string) ($data['recipeCategory'] ?? ''),
                        'recipeCuisine'  => (string) ($data['recipeCuisine'] ?? ''),
                        'ingredients'    => $this->ensure_string_list($data['recipeIngredient'] ?? []),
                        'instructions'   => $this->ensure_string_list($data['recipeInstructions'] ?? []),
                    ];
                    break;
                case 'event':
                    $entry['data'] = [
                        'name'             => (string) ($data['name'] ?? ''),
                        'description'      => (string) ($data['description'] ?? ''),
                        'startDate'        => (string) ($data['startDate'] ?? ''),
                        'endDate'          => (string) ($data['endDate'] ?? ''),
                        'eventStatus'      => (string) ($data['eventStatus'] ?? ''),
                        'locationName'     => (string) ($data['location']['name'] ?? ''),
                        'locationAddress'  => (string) ($data['location']['address'] ?? ''),
                        'organizerName'    => (string) ($data['organizer']['name'] ?? ''),
                    ];
                    break;
                case 'local_business':
                    $entry['data'] = [
                        'name'            => (string) ($data['name'] ?? ''),
                        'description'     => (string) ($data['description'] ?? ''),
                        'telephone'       => (string) ($data['telephone'] ?? ''),
                        'priceRange'      => (string) ($data['priceRange'] ?? ''),
                        'streetAddress'   => (string) ($data['address']['streetAddress'] ?? ''),
                        'addressLocality' => (string) ($data['address']['addressLocality'] ?? ''),
                        'addressRegion'   => (string) ($data['address']['addressRegion'] ?? ''),
                        'postalCode'      => (string) ($data['address']['postalCode'] ?? ''),
                        'addressCountry'  => (string) ($data['address']['addressCountry'] ?? ''),
                    ];
                    break;
                case 'job_posting':
                    $entry['data'] = [
                        'title'         => (string) ($data['title'] ?? ''),
                        'description'   => (string) ($data['description'] ?? ''),
                        'datePosted'    => (string) ($data['datePosted'] ?? ''),
                        'validThrough'  => (string) ($data['validThrough'] ?? ''),
                        'employmentType' => (string) ($data['employmentType'] ?? ''),
                        'hiringOrgName' => (string) ($data['hiringOrganization']['name'] ?? ''),
                        'hiringOrgUrl'  => (string) ($data['hiringOrganization']['sameAs'] ?? ''),
                        'salaryMin'     => (string) ($data['baseSalary']['value']['minValue'] ?? ''),
                        'salaryMax'     => (string) ($data['baseSalary']['value']['maxValue'] ?? ''),
                        'salaryCurrency' => (string) ($data['baseSalary']['currency'] ?? ''),
                    ];
                    break;
                case 'video':
                    $entry['data'] = [
                        'name'         => (string) ($data['name'] ?? ''),
                        'description'  => (string) ($data['description'] ?? ''),
                        'thumbnailUrl' => (string) ($data['thumbnailUrl'] ?? ''),
                        'contentUrl'   => (string) ($data['contentUrl'] ?? ''),
                        'embedUrl'     => (string) ($data['embedUrl'] ?? ''),
                        'uploadDate'   => (string) ($data['uploadDate'] ?? ''),
                        'duration'     => (string) ($data['duration'] ?? ''),
                    ];
                    break;
                case 'review':
                    $entry['data'] = [
                        'itemType'    => (string) ($data['itemReviewed']['@type'] ?? 'Thing'),
                        'itemName'    => (string) ($data['itemReviewed']['name'] ?? ''),
                        'ratingValue' => (string) ($data['reviewRating']['ratingValue'] ?? ''),
                        'reviewBody'  => (string) ($data['reviewBody'] ?? ''),
                        'authorName'  => (string) ($data['author']['name'] ?? ''),
                    ];
                    break;
                case 'custom':
                default:
                    // Preserve everything as raw key/value rows so the
                    // user can edit later in the Custom builder UI.
                    $properties = [];
                    foreach ($data as $k => $v) {
                        if (! is_string($k) || $k === '' || $k[0] === '@') continue;
                        $properties[] = [
                            'name'  => (string) $k,
                            'value' => is_array($v) ? wp_json_encode($v) : (string) $v,
                        ];
                    }
                    $entry['data'] = ['properties' => $properties];
                    break;
            }

            // Drop empty data — no point creating a schema with nothing in it.
            if ($this->is_data_empty($entry['data'])) {
                continue;
            }
            $entries[] = $entry;
            $seen_templates[$template_id] = true;
        }

        // ── Format 2: rank_math_rich_snippet (Free / legacy)
        $rich = strtolower(trim((string) get_post_meta($post_id, 'rank_math_rich_snippet', true)));
        if ($rich !== '' && $rich !== 'off' && $rich !== 'none') {
            $legacy_entry = $this->build_legacy_rich_snippet($post_id, $rich);
            if ($legacy_entry !== null && empty($seen_templates[$legacy_entry['template']])) {
                $entries[] = $legacy_entry;
                $seen_templates[$legacy_entry['template']] = true;
            }
        }

        // ── Format 3: rank-math/faq-block + rank-math/howto-block in content
        $post = get_post($post_id);
        if ($post instanceof WP_Post && function_exists('parse_blocks') && $post->post_content !== '') {
            $blocks = parse_blocks($post->post_content);
            $questions = $this->extract_rm_faq_questions($blocks);
            if (! empty($questions) && empty($seen_templates['faq'])) {
                $entries[] = [
                    'id'       => wp_generate_uuid4(),
                    'template' => 'faq',
                    'type'     => 'FAQPage',
                    'enabled'  => true,
                    'data'     => ['questions' => $questions],
                ];
                $seen_templates['faq'] = true;
            }
        }

        return $entries;
    }

    /**
     * Build an EBQ schema entry from Rank Math Free's flat
     * `rank_math_snippet_*` meta keys, given a `rank_math_rich_snippet`
     * type slug. Per-type field maps cover the high-value cases —
     * unknowns fall through to a `custom` entry so we never lose
     * the source data.
     */
    private function build_legacy_rich_snippet(int $post_id, string $rich_type): ?array
    {
        // RM Free's slug → EBQ template id.
        $template_map = [
            'article'    => 'article',
            'book'       => 'book',
            'course'     => 'course',
            'event'      => 'event',
            'faq'        => 'faq',
            'jobposting' => 'job_posting',
            'local'      => 'local_business',
            'movie'      => 'movie',
            'music'      => 'music_album',
            'person'     => 'person',
            'product'    => 'product',
            'recipe'     => 'recipe',
            'review'     => 'review',
            'service'    => 'service',
            'software'   => 'software',
            'video'      => 'video',
        ];
        $template_id = $template_map[$rich_type] ?? 'custom';

        // Helper to fetch a flat snippet field with a default.
        $g = static fn (string $k, string $default = '') => (string) (get_post_meta($post_id, 'rank_math_snippet_' . $k, true) ?: $default);

        $type = '';
        $data = [];

        switch ($template_id) {
            case 'article':
                $type = $g('article_type', 'Article'); // Article / BlogPosting / NewsArticle
                $data = [
                    'headline'      => $g('name', '%title%'),
                    'description'   => $g('desc', '%excerpt%'),
                    'image'         => '%featured_image%',
                    'datePublished' => '%date%',
                    'dateModified'  => '%modified%',
                    'authorName'    => '%author%',
                ];
                break;
            case 'product':
                $type = 'Product';
                $data = [
                    'name'          => $g('name'),
                    'description'   => $g('desc'),
                    'sku'           => $g('product_sku'),
                    'brand'         => $g('product_brand'),
                    'price'         => $g('product_price'),
                    'currency'      => $g('product_currency'),
                ];
                break;
            case 'recipe':
                $type = 'Recipe';
                $data = [
                    'name'           => $g('recipe_name', $g('name')),
                    'description'    => $g('recipe_desc', $g('desc')),
                    'recipeYield'    => $g('recipe_yield'),
                    'recipeCategory' => $g('recipe_category'),
                    'recipeCuisine'  => $g('recipe_cuisine'),
                    'prepTime'       => $g('recipe_preptime'),
                    'cookTime'       => $g('recipe_cooktime'),
                    'totalTime'      => $g('recipe_totaltime'),
                    'ingredients'    => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $g('recipe_single_ingredients'))), 'strlen')),
                    'instructions'   => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $g('recipe_single_instructions'))), 'strlen')),
                    'calories'       => $g('recipe_calories'),
                ];
                break;
            case 'event':
                $type = 'Event';
                $data = [
                    'name'             => $g('event_name', $g('name')),
                    'description'      => $g('event_desc', $g('desc')),
                    'startDate'        => $g('event_startdate'),
                    'endDate'          => $g('event_enddate'),
                    'eventStatus'      => $g('event_status', 'EventScheduled'),
                    'locationName'     => $g('event_address'),
                    'organizerName'    => $g('event_performer'),
                    'offerPrice'       => $g('event_ticketurl'), // RM Free uses simple offer URL
                    'offerCurrency'    => $g('event_currency'),
                ];
                break;
            case 'job_posting':
                $type = 'JobPosting';
                $data = [
                    'title'         => $g('jobposting_title', $g('name')),
                    'description'   => $g('desc'),
                    'datePosted'    => $g('jobposting_startdate', '%date%'),
                    'validThrough'  => $g('jobposting_expirydate'),
                    'employmentType' => $g('jobposting_employment_type'),
                    'hiringOrgName' => $g('jobposting_organization'),
                    'hiringOrgUrl'  => $g('jobposting_url'),
                    'salaryMin'     => $g('jobposting_salary'),
                    'salaryCurrency' => $g('jobposting_currency'),
                ];
                break;
            case 'local_business':
                $type = $g('local_business_type', 'LocalBusiness');
                $data = [
                    'name'            => $g('name'),
                    'description'     => $g('desc'),
                    'telephone'       => $g('local_phone'),
                    'priceRange'      => $g('local_price_range'),
                    'streetAddress'   => $g('local_address_streetaddress'),
                    'addressLocality' => $g('local_address_addresslocality'),
                    'addressRegion'   => $g('local_address_addressregion'),
                    'postalCode'      => $g('local_address_postalcode'),
                    'addressCountry'  => $g('local_address_addresscountry'),
                ];
                break;
            case 'video':
                $type = 'VideoObject';
                $data = [
                    'name'         => $g('video_name', $g('name')),
                    'description'  => $g('desc'),
                    'thumbnailUrl' => $g('video_thumbnail', '%featured_image%'),
                    'contentUrl'   => $g('video_content_url'),
                    'embedUrl'     => $g('video_embed_url'),
                    'uploadDate'   => $g('video_uploaddate', '%date%'),
                    'duration'     => $g('video_duration'),
                ];
                break;
            case 'review':
                $type = 'Review';
                $data = [
                    'itemType'    => $g('review_item_type', 'Thing'),
                    'itemName'    => $g('review_name', $g('name')),
                    'ratingValue' => $g('review_rating_value'),
                    'reviewBody'  => $g('desc'),
                    'authorName'  => $g('review_author', '%author%'),
                ];
                break;
            case 'book':
                $type = 'Book';
                $data = [
                    'name'        => $g('name'),
                    'author'      => $g('book_author', '%author%'),
                    'isbn'        => $g('book_isbn'),
                    'description' => $g('desc'),
                ];
                break;
            case 'course':
                $type = 'Course';
                $data = [
                    'name'         => $g('name'),
                    'description'  => $g('desc'),
                    'providerName' => $g('course_provider'),
                    'providerUrl'  => $g('course_provider_url'),
                ];
                break;
            case 'person':
                $type = 'Person';
                $data = [
                    'name'        => $g('name'),
                    'jobTitle'    => $g('person_job_title'),
                    'email'       => $g('person_email'),
                    'telephone'   => $g('person_phone'),
                    'url'         => $g('person_url'),
                ];
                break;
            case 'service':
                $type = 'Service';
                $data = [
                    'name'        => $g('name'),
                    'description' => $g('desc'),
                    'serviceType' => $g('service_type'),
                    'price'       => $g('service_price'),
                    'currency'    => $g('service_currency'),
                ];
                break;
            case 'software':
                $type = 'SoftwareApplication';
                $data = [
                    'name'                => $g('software_name', $g('name')),
                    'operatingSystem'     => $g('software_operating_system'),
                    'applicationCategory' => $g('software_application_category'),
                    'price'               => $g('software_price'),
                    'currency'            => $g('software_price_currency'),
                ];
                break;
            case 'movie':
                $type = 'Movie';
                $data = [
                    'name'         => $g('name'),
                    'description'  => $g('desc'),
                    'datePublished' => $g('movie_date'),
                    'director'     => $g('movie_director'),
                    'duration'     => $g('movie_duration'),
                ];
                break;
            case 'music_album':
                $type = 'MusicAlbum';
                $data = [
                    'name'          => $g('name'),
                    'byArtist'      => $g('music_artist'),
                    'datePublished' => $g('music_release_date'),
                ];
                break;
            case 'faq':
                // Free FAQ in this storage format is rare; the block-walker
                // path below catches the common case.
                $type = 'FAQPage';
                $data = ['questions' => []];
                break;
            case 'custom':
            default:
                $type = ucfirst($rich_type);
                // Sweep all rank_math_snippet_* keys into custom properties.
                $properties = [];
                foreach ((array) get_post_meta($post_id) as $meta_key => $meta_vals) {
                    if (strpos((string) $meta_key, 'rank_math_snippet_') !== 0) continue;
                    $val = is_array($meta_vals) ? (string) reset($meta_vals) : (string) $meta_vals;
                    if ($val === '') continue;
                    $properties[] = [
                        'name'  => substr((string) $meta_key, strlen('rank_math_snippet_')),
                        'value' => $val,
                    ];
                }
                $data = ['properties' => $properties];
                break;
        }

        if ($this->is_data_empty($data)) return null;

        return [
            'id'       => wp_generate_uuid4(),
            'template' => $template_id,
            'type'     => $type ?: 'Thing',
            'enabled'  => true,
            'data'     => $data,
        ];
    }

    /**
     * Recursively walk the block tree pulling `rank-math/faq-block`
     * questions into the EBQ FAQ shape. Block stores `attrs.questions`
     * as a list of `{title, content}` (varies by RM version — also
     * accepts `{question, answer}`).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array{question:string, answer:string}>
     */
    private function extract_rm_faq_questions(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            $name = (string) ($block['blockName'] ?? '');
            if ($name === 'rank-math/faq-block') {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $questions = is_array($attrs['questions'] ?? null) ? $attrs['questions'] : [];
                foreach ($questions as $q) {
                    if (! is_array($q)) continue;
                    $question = trim((string) ($q['title'] ?? $q['question'] ?? ''));
                    $answer   = trim((string) ($q['content'] ?? $q['answer']  ?? ''));
                    if ($question === '' || $answer === '') continue;
                    $out[] = ['question' => $question, 'answer' => $answer];
                }
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $out = array_merge($out, $this->extract_rm_faq_questions($block['innerBlocks']));
            }
        }
        return $out;
    }

    /**
     * Rank Math stores arrays via PHP serialization (the WP postmeta
     * standard), so `get_post_meta(..., true)` already unserializes them.
     * This helper coerces anything weird back into an array.
     *
     * @param  mixed  $value
     * @return array<int|string, mixed>
     */
    private function maybe_unserialize_array($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            $maybe = maybe_unserialize($value);
            if (is_array($maybe)) return $maybe;
        }
        return [];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function ensure_string_list($value): array
    {
        if (! is_array($value)) return [];
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $clean = trim($item);
                if ($clean !== '') $out[] = $clean;
            } elseif (is_array($item) && ! empty($item['text'])) {
                $clean = trim((string) $item['text']);
                if ($clean !== '') $out[] = $clean;
            }
        }
        return $out;
    }

    /** Recursively true when every leaf is empty. */
    private function is_data_empty(array $data): bool
    {
        foreach ($data as $v) {
            if (is_array($v)) {
                if (! $this->is_data_empty($v)) return false;
            } elseif ($v !== null && $v !== '' && $v !== false) {
                return false;
            }
        }
        return true;
    }
}
