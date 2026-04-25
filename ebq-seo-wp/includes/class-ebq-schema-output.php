<?php
/**
 * JSON-LD structured data emitter.
 *
 * Emits a single `<script type="application/ld+json">@graph</script>` per page
 * containing the pieces below, each gated by an `is_needed()` check so the
 * graph is only as wide as the current request. Mirrors the modular layout of
 * Yoast's `src/generators/schema/` without the full DI container — one method
 * per piece, aggregated by `output()`.
 *
 *   • WebSite         — site identity + sitelinks search box (every request)
 *   • Organization    — publisher (every request)
 *   • Person          — author Person node (singular posts)
 *   • PrimaryImage    — ImageObject for the post's featured image
 *   • WebPage         — page node (every request)
 *   • BreadcrumbList  — Home › ancestors › current (singular and term archives)
 *   • Article         — singular posts (default; switchable via meta override)
 *   • Product         — singular WooCommerce products
 *   • FAQPage         — auto from FAQ blocks (Yoast / generic / Stackable)
 *   • HowTo           — auto from HowTo blocks
 *
 * Coexistence: stays silent when another major SEO plugin is active, so a site
 * never ends up with two graphs.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Schema_Output
{
    /** Block names we recognise as FAQ pieces. */
    private const FAQ_BLOCKS = [
        'yoast/faq-block',
        'rank-math/faq-block',
        'ugb/faq',
        'ebq/faq',
    ];

    /** Block names we recognise as HowTo pieces. */
    private const HOWTO_BLOCKS = [
        'yoast/how-to-block',
        'rank-math/howto-block',
        'ebq/howto',
    ];

    public function register(): void
    {
        if (EBQ_Meta_Output::another_seo_plugin_is_active()) {
            return;
        }
        add_action('wp_head', [$this, 'output'], 5);
    }

    public function output(): void
    {
        $post_id = is_singular() ? (int) get_queried_object_id() : 0;

        if ($post_id > 0 && EBQ_Meta_Fields::get($post_id, '_ebq_schema_disabled', false)) {
            return;
        }

        $graph = [];
        $graph[] = $this->website_node();
        $graph[] = $this->organization_node();
        $graph[] = $this->webpage_node($post_id);

        $primaryImage = $this->primary_image_node($post_id);
        if ($primaryImage !== null) {
            $graph[] = $primaryImage;
        }

        $breadcrumbs = $this->breadcrumb_node($post_id);
        if ($breadcrumbs !== null) {
            $graph[] = $breadcrumbs;
        }

        if ($post_id > 0) {
            $author = $this->person_node($post_id);
            if ($author !== null) {
                $graph[] = $author;
            }

            // User-configured schemas (post-meta `_ebq_schemas`). If the user
            // has set any here, those take precedence over the auto-detected
            // Article/Product node and over block-driven FAQ/HowTo for the
            // same type — so we never emit two of the same kind.
            $user_schemas = $this->user_schemas($post_id);
            $user_types = [];
            foreach ($user_schemas as $entry) {
                $node = EBQ_Schema_Templates::render($entry, $post_id);
                if ($node !== null) {
                    $graph[] = $node;
                    $user_types[(string) ($node['@type'] ?? '')] = true;
                }
            }

            // Suppress the auto-Article/Product if the user already wrote one.
            $skip_primary_types = ['Article', 'BlogPosting', 'NewsArticle', 'Product', 'Event', 'Recipe', 'LocalBusiness', 'Restaurant', 'Store', 'ProfessionalService', 'MedicalBusiness', 'JobPosting', 'SoftwareApplication'];
            $primary_already_user = false;
            foreach ($skip_primary_types as $t) {
                if (! empty($user_types[$t])) { $primary_already_user = true; break; }
            }

            if (! $primary_already_user) {
                $override = (string) EBQ_Meta_Fields::get($post_id, '_ebq_schema_type', '');
                $type = $override !== '' ? $override : $this->detect_type($post_id);
                $node = $this->primary_node($post_id, $type);
                if ($node !== null) {
                    $graph[] = $node;
                }
            }

            // Block-driven FAQ/HowTo are still emitted, but a user-defined
            // FAQPage suppresses the block-driven one to avoid duplicates.
            foreach ($this->block_driven_nodes($post_id) as $extra) {
                $extraType = (string) ($extra['@type'] ?? '');
                if ($extraType === 'FAQPage' && ! empty($user_types['FAQPage'])) continue;
                if ($extraType === 'HowTo' && ! empty($user_types['HowTo'])) continue;
                $graph[] = $extra;
            }
        }

        $doc = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values(array_filter($graph)),
        ];

        echo "<script type=\"application/ld+json\">".wp_json_encode($doc, JSON_UNESCAPED_UNICODE)."</script>\n";
    }

    /* ─── Pieces ─────────────────────────────────────────────────── */

    private function website_node(): array
    {
        $home = home_url('/');

        return [
            '@type'           => 'WebSite',
            '@id'             => $home.'#website',
            'url'             => $home,
            'name'            => (string) get_bloginfo('name'),
            'description'     => (string) get_bloginfo('description'),
            'inLanguage'      => str_replace('_', '-', (string) get_locale()),
            'publisher'       => ['@id' => $home.'#organization'],
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $home.'?s={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private function organization_node(): array
    {
        $home = home_url('/');
        $node = [
            '@type' => 'Organization',
            '@id'   => $home.'#organization',
            'url'   => $home,
            'name'  => (string) get_bloginfo('name'),
        ];
        $icon = get_site_icon_url();
        if ($icon) {
            $node['logo'] = [
                '@type'      => 'ImageObject',
                '@id'        => $home.'#logo',
                'url'        => $icon,
                'contentUrl' => $icon,
            ];
            $node['image'] = ['@id' => $home.'#logo'];
        }

        return $node;
    }

    private function person_node(int $post_id): ?array
    {
        $author_id = (int) get_post_field('post_author', $post_id);
        if ($author_id <= 0) {
            return null;
        }
        $name = trim((string) get_the_author_meta('display_name', $author_id));
        if ($name === '') {
            return null;
        }

        $home = home_url('/');
        $node = [
            '@type' => 'Person',
            '@id'   => $home.'#/schema/person/'.md5((string) $author_id),
            'name'  => $name,
            'url'   => (string) get_author_posts_url($author_id),
        ];

        $description = trim((string) get_the_author_meta('description', $author_id));
        if ($description !== '') {
            $node['description'] = $description;
        }

        $avatar = get_avatar_url($author_id, ['size' => 192]);
        if ($avatar) {
            $node['image'] = [
                '@type'      => 'ImageObject',
                '@id'        => $home.'#/schema/person/image/'.md5((string) $author_id),
                'url'        => $avatar,
                'contentUrl' => $avatar,
                'caption'    => $name,
            ];
        }

        return $node;
    }

    private function primary_image_node(int $post_id): ?array
    {
        if ($post_id <= 0 || ! has_post_thumbnail($post_id)) {
            return null;
        }
        $thumbnail_id = (int) get_post_thumbnail_id($post_id);
        $url = (string) wp_get_attachment_image_url($thumbnail_id, 'full');
        if ($url === '') {
            return null;
        }
        $meta = wp_get_attachment_metadata($thumbnail_id);
        $page_url = (string) get_permalink($post_id);

        $node = [
            '@type'      => 'ImageObject',
            '@id'        => $page_url.'#primaryimage',
            'url'        => $url,
            'contentUrl' => $url,
        ];
        if (is_array($meta)) {
            if (! empty($meta['width']))  $node['width']  = (int) $meta['width'];
            if (! empty($meta['height'])) $node['height'] = (int) $meta['height'];
        }
        $caption = (string) wp_get_attachment_caption($thumbnail_id);
        if ($caption !== '') {
            $node['caption'] = $caption;
        }

        return $node;
    }

    private function webpage_node(int $post_id): array
    {
        $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url(add_query_arg(null, null));

        $node = [
            '@type'      => 'WebPage',
            '@id'        => $url.'#webpage',
            'url'        => $url,
            'name'       => wp_get_document_title(),
            'isPartOf'   => ['@id' => home_url('/').'#website'],
            'inLanguage' => str_replace('_', '-', (string) get_locale()),
        ];

        if ($post_id > 0) {
            if (has_post_thumbnail($post_id)) {
                $node['primaryImageOfPage'] = ['@id' => $url.'#primaryimage'];
                $node['thumbnailUrl'] = (string) get_the_post_thumbnail_url($post_id, 'full');
            }
            $node['datePublished'] = (string) get_the_date('c', $post_id);
            $node['dateModified']  = (string) get_the_modified_date('c', $post_id);
            $node['breadcrumb']    = ['@id' => home_url('/').'#breadcrumbs-'.$post_id];
        }

        return $node;
    }

    private function breadcrumb_node(int $post_id): ?array
    {
        // Per-post override wins. Mode='custom' fully replaces the auto trail;
        // mode='auto' (or empty) falls through to the default Home → ancestors
        // → current logic. We still skip hidden items and apply position
        // numbering at output time so the user only worries about the list.
        if ($post_id > 0) {
            $custom = $this->custom_breadcrumb_items($post_id);
            if ($custom !== null) {
                return $custom;
            }
        }

        $items = [];
        $position = 1;

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => __('Home', 'ebq-seo'),
            'item'     => home_url('/'),
        ];

        if ($post_id > 0) {
            $ancestors = array_reverse((array) get_post_ancestors($post_id));
            foreach ($ancestors as $ancestor_id) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => (string) get_the_title($ancestor_id),
                    'item'     => (string) get_permalink($ancestor_id),
                ];
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => (string) get_the_title($post_id),
            ];
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => $term->name,
                    'item'     => (string) get_term_link($term),
                ];
            }
        } else {
            return null;
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => home_url('/').'#breadcrumbs-'.($post_id > 0 ? $post_id : 'home'),
            'itemListElement' => $items,
        ];
    }

    /**
     * Read the per-post `_ebq_breadcrumbs` override and turn it into a
     * BreadcrumbList node. Returns null when the user has chosen 'auto'
     * mode or no override is saved — caller falls back to the default
     * Home → ancestors → current trail.
     */
    private function custom_breadcrumb_items(int $post_id): ?array
    {
        $raw = (string) EBQ_Meta_Fields::get($post_id, '_ebq_breadcrumbs', '');
        if ($raw === '') return null;
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) return null;
        if (($decoded['mode'] ?? 'auto') !== 'custom') return null;
        $items = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
        if (empty($items)) return null;

        $list = [];
        $position = 1;
        $url = (string) get_permalink($post_id);
        $count = count(array_filter($items, fn ($i) => is_array($i) && empty($i['hidden'])));
        $emitted = 0;

        foreach ($items as $item) {
            if (! is_array($item) || ! empty($item['hidden'])) continue;
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') continue;
            $entry = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => wp_strip_all_tags($name),
            ];
            $emitted++;
            // Schema spec: the LAST item should NOT carry an `item` URL —
            // it represents the current page. So we skip URL on the final
            // entry even if the user supplied one.
            if (! empty($item['url']) && $emitted < $count) {
                $entry['item'] = (string) esc_url_raw((string) $item['url']);
            }
            $list[] = $entry;
        }

        if (empty($list)) return null;

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $url . '#breadcrumbs-' . $post_id,
            'itemListElement' => $list,
        ];
    }

    /**
     * Decode the per-post `_ebq_schemas` JSON into an ordered list of entries.
     * Returns [] for posts with no user-configured schemas (the common case).
     *
     * @return list<array<string, mixed>>
     */
    private function user_schemas(int $post_id): array
    {
        $raw = (string) EBQ_Meta_Fields::get($post_id, '_ebq_schemas', '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    private function detect_type(int $post_id): string
    {
        if (function_exists('wc_get_product') && get_post_type($post_id) === 'product') {
            return 'Product';
        }
        if (get_post_type($post_id) === 'page') {
            return 'WebPage';
        }

        return 'Article';
    }

    private function primary_node(int $post_id, string $type): ?array
    {
        $url = (string) get_permalink($post_id);

        if ($type === 'Product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if (! $product) {
                return null;
            }
            $node = [
                '@type'       => 'Product',
                '@id'         => $url.'#product',
                'name'        => (string) $product->get_name(),
                'description' => wp_strip_all_tags((string) ($product->get_short_description() ?: $product->get_description())),
                'sku'         => (string) $product->get_sku(),
                'mainEntityOfPage' => ['@id' => $url.'#webpage'],
            ];
            if (has_post_thumbnail($post_id)) {
                $node['image'] = ['@id' => $url.'#primaryimage'];
            }
            $price = $product->get_price();
            if ($price !== '' && function_exists('get_woocommerce_currency')) {
                $node['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => (string) $price,
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url'           => $url,
                ];
            }

            return $node;
        }

        if ($type === 'WebPage') {
            return null; // already emitted
        }

        $author_id = (int) get_post_field('post_author', $post_id);

        $node = [
            '@type'           => $type === 'Article' ? 'Article' : $type,
            '@id'             => $url.'#article',
            'isPartOf'        => ['@id' => $url.'#webpage'],
            'mainEntityOfPage'=> ['@id' => $url.'#webpage'],
            'headline'        => (string) get_the_title($post_id),
            'datePublished'   => (string) get_the_date('c', $post_id),
            'dateModified'    => (string) get_the_modified_date('c', $post_id),
            'author'          => ['@id' => home_url('/').'#/schema/person/'.md5((string) $author_id)],
            'publisher'       => ['@id' => home_url('/').'#organization'],
        ];

        if (has_post_thumbnail($post_id)) {
            $node['image']     = ['@id' => $url.'#primaryimage'];
            $node['thumbnailUrl'] = (string) get_the_post_thumbnail_url($post_id, 'full');
        }

        $description = (string) EBQ_Meta_Fields::get($post_id, '_ebq_description', '');
        if ($description !== '') {
            $node['description'] = $description;
        }

        // Word count from raw content.
        $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));
        if ($words > 0) {
            $node['wordCount'] = $words;
        }

        // Primary category → articleSection.
        $section = $this->primary_section($post_id);
        if ($section !== '') {
            $node['articleSection'] = $section;
        }

        $node['inLanguage'] = str_replace('_', '-', (string) get_locale());

        return $node;
    }

    private function primary_section(int $post_id): string
    {
        $cats = get_the_category($post_id);
        if (! is_array($cats) || empty($cats)) {
            return '';
        }
        $primary = $cats[0];
        if ($primary instanceof WP_Term) {
            return (string) $primary->name;
        }

        return '';
    }

    /**
     * Walk the post's blocks and assemble FAQ + HowTo nodes from recognised
     * block names. Uses an exact-match allowlist (FAQ_BLOCKS / HOWTO_BLOCKS)
     * to avoid false positives like a "faq-button" widget.
     *
     * @return list<array<string, mixed>>
     */
    private function block_driven_nodes(int $post_id): array
    {
        $content = (string) get_post_field('post_content', $post_id);
        if ($content === '' || ! function_exists('parse_blocks')) {
            return [];
        }
        $blocks = parse_blocks($content);
        if (empty($blocks)) {
            return [];
        }

        $faq_items = [];
        $howto_steps = [];

        $walk = function (array $blocks) use (&$walk, &$faq_items, &$howto_steps): void {
            foreach ($blocks as $block) {
                $name = (string) ($block['blockName'] ?? '');

                if (in_array($name, self::FAQ_BLOCKS, true)) {
                    $entries = $this->faq_entries_from_block($block);
                    foreach ($entries as $entry) {
                        $faq_items[] = $entry;
                    }
                }

                if (in_array($name, self::HOWTO_BLOCKS, true)) {
                    $steps = $this->howto_steps_from_block($block);
                    foreach ($steps as $step) {
                        $howto_steps[] = $step;
                    }
                }

                if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    $walk($block['innerBlocks']);
                }
            }
        };
        $walk($blocks);

        $out = [];

        if (! empty($faq_items)) {
            $out[] = [
                '@type'      => 'FAQPage',
                '@id'        => (string) get_permalink($post_id).'#faq',
                'mainEntity' => $faq_items,
            ];
        }

        if (! empty($howto_steps)) {
            $out[] = [
                '@type' => 'HowTo',
                '@id'   => (string) get_permalink($post_id).'#howto',
                'name'  => (string) get_the_title($post_id),
                'step'  => $howto_steps,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array<string, mixed>>
     */
    private function faq_entries_from_block(array $block): array
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        // Yoast/Rank Math style: attrs.questions = [{question, answer}, ...]
        $questions = $attrs['questions'] ?? null;
        if (is_array($questions)) {
            $out = [];
            foreach ($questions as $q) {
                if (! is_array($q)) continue;
                $question = trim((string) ($q['question'] ?? $q['title'] ?? ''));
                $answer   = trim((string) ($q['answer']   ?? $q['content'] ?? ''));
                if ($question === '' || $answer === '') continue;
                $out[] = [
                    '@type'          => 'Question',
                    'name'           => wp_strip_all_tags($question),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => wp_strip_all_tags($answer),
                    ],
                ];
            }
            if (! empty($out)) {
                return $out;
            }
        }

        // Fallback: parse innerHTML for question/answer halves.
        $html = (string) ($block['innerHTML'] ?? '');
        if ($html === '') {
            return [];
        }
        $stripped = wp_strip_all_tags($html);
        if ($stripped === '') {
            return [];
        }

        return [[
            '@type'          => 'Question',
            'name'           => mb_substr($stripped, 0, 140),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $stripped],
        ]];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array<string, mixed>>
     */
    private function howto_steps_from_block(array $block): array
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        $steps = $attrs['steps'] ?? $attrs['items'] ?? null;
        if (is_array($steps)) {
            $out = [];
            foreach ($steps as $step) {
                if (! is_array($step)) continue;
                $name = trim((string) ($step['name'] ?? $step['title'] ?? ''));
                $text = trim((string) ($step['text'] ?? $step['description'] ?? $step['content'] ?? $name));
                if ($text === '') continue;
                $node = ['@type' => 'HowToStep', 'text' => wp_strip_all_tags($text)];
                if ($name !== '' && $name !== $text) {
                    $node['name'] = wp_strip_all_tags($name);
                }
                $out[] = $node;
            }
            if (! empty($out)) {
                return $out;
            }
        }

        $html = (string) ($block['innerHTML'] ?? '');
        $stripped = wp_strip_all_tags($html);
        if ($stripped === '') {
            return [];
        }

        return [['@type' => 'HowToStep', 'text' => $stripped]];
    }
}
