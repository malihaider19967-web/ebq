<?php
/**
 * JSON-LD structured-data emitter.
 *
 * Emits a single `<script type="application/ld+json">@graph</script>` per
 * page containing:
 *   • WebSite + Organization (site-wide, always)
 *   • WebPage (every page)
 *   • BreadcrumbList (built from WP's ancestors / category / archive)
 *   • Article (singular posts) OR Product (singular WooCommerce products)
 *     — override per-post via `_ebq_schema_type`, disable via
 *     `_ebq_schema_disabled`.
 *
 * Detects FAQ and HowTo blocks in post content and injects the matching
 * FAQPage / HowTo schema automatically.
 *
 * Coexistence: if another SEO plugin is active we skip, to avoid duplicate
 * JSON-LD graphs. The Yoast/Rank Math/AIOSEO importer will migrate users so
 * they can uninstall the incumbent.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Schema_Output
{
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

        $breadcrumbs = $this->breadcrumb_node($post_id);
        if ($breadcrumbs !== null) {
            $graph[] = $breadcrumbs;
        }

        if ($post_id > 0) {
            $override = (string) EBQ_Meta_Fields::get($post_id, '_ebq_schema_type', '');
            $type = $override !== '' ? $override : $this->detect_type($post_id);
            $node = $this->primary_node($post_id, $type);
            if ($node !== null) {
                $graph[] = $node;
            }

            foreach ($this->block_driven_nodes($post_id) as $extra) {
                $graph[] = $extra;
            }
        }

        $doc = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter($graph)),
        ];

        echo "<script type=\"application/ld+json\">".wp_json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."</script>\n";
    }

    private function website_node(): array
    {
        $home = home_url('/');

        return [
            '@type' => 'WebSite',
            '@id' => $home.'#website',
            'url' => $home,
            'name' => (string) get_bloginfo('name'),
            'description' => (string) get_bloginfo('description'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
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
            '@id' => $home.'#organization',
            'url' => $home,
            'name' => (string) get_bloginfo('name'),
        ];
        $icon = get_site_icon_url();
        if ($icon) {
            $node['logo'] = [
                '@type' => 'ImageObject',
                'url' => $icon,
            ];
        }

        return $node;
    }

    private function webpage_node(int $post_id): array
    {
        $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url(add_query_arg(null, null));

        return [
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => wp_get_document_title(),
            'isPartOf' => ['@id' => home_url('/').'#website'],
            'inLanguage' => str_replace('_', '-', (string) get_locale()),
        ];
    }

    private function breadcrumb_node(int $post_id): ?array
    {
        $items = [];
        $position = 1;

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Home', 'ebq-seo'),
            'item' => home_url('/'),
        ];

        if ($post_id > 0) {
            $ancestors = array_reverse((array) get_post_ancestors($post_id));
            foreach ($ancestors as $ancestor_id) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => (string) get_the_title($ancestor_id),
                    'item' => (string) get_permalink($ancestor_id),
                ];
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => (string) get_the_title($post_id),
                'item' => (string) get_permalink($post_id),
            ];
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $term->name,
                    'item' => (string) get_term_link($term),
                ];
            }
        } else {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => home_url('/').'#breadcrumbs-'.($post_id > 0 ? $post_id : 'home'),
            'itemListElement' => $items,
        ];
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

            return [
                '@type' => 'Product',
                '@id' => $url.'#product',
                'name' => (string) $product->get_name(),
                'description' => wp_strip_all_tags((string) ($product->get_short_description() ?: $product->get_description())),
                'image' => $product->get_image_id() ? (string) wp_get_attachment_url($product->get_image_id()) : null,
                'sku' => (string) $product->get_sku(),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $product->get_price(),
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $url,
                ],
            ];
        }

        if ($type === 'WebPage') {
            return null; // already emitted
        }

        // Article default.
        $image = has_post_thumbnail($post_id) ? (string) get_the_post_thumbnail_url($post_id, 'full') : '';
        $author_id = (int) get_post_field('post_author', $post_id);
        $author_name = (string) get_the_author_meta('display_name', $author_id);

        $node = [
            '@type' => 'Article',
            '@id' => $url.'#article',
            'isPartOf' => ['@id' => $url.'#webpage'],
            'mainEntityOfPage' => ['@id' => $url.'#webpage'],
            'headline' => (string) get_the_title($post_id),
            'datePublished' => (string) get_the_date('c', $post_id),
            'dateModified' => (string) get_the_modified_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => $author_name ?: (string) get_bloginfo('name'),
            ],
            'publisher' => ['@id' => home_url('/').'#organization'],
        ];
        if ($image !== '') {
            $node['image'] = $image;
        }
        $description = EBQ_Meta_Fields::get($post_id, '_ebq_description', '');
        if ($description !== '') {
            $node['description'] = (string) $description;
        }

        return $node;
    }

    /**
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

        $out = [];
        $faq_items = [];
        $howto_steps = [];

        $walk = function (array $blocks) use (&$walk, &$faq_items, &$howto_steps): void {
            foreach ($blocks as $block) {
                $name = (string) ($block['blockName'] ?? '');
                if ($name === 'core/details' || $name === 'core/group') {
                    // common wrappers; recurse inner
                }
                if (strpos($name, 'faq') !== false) {
                    $inner = wp_strip_all_tags((string) ($block['innerHTML'] ?? ''));
                    $faq_items[] = ['@type' => 'Question', 'name' => mb_substr($inner, 0, 140), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $inner]];
                }
                if (strpos($name, 'howto') !== false || strpos($name, 'how-to') !== false) {
                    $inner = wp_strip_all_tags((string) ($block['innerHTML'] ?? ''));
                    $howto_steps[] = ['@type' => 'HowToStep', 'text' => $inner];
                }
                if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    $walk($block['innerBlocks']);
                }
            }
        };
        $walk($blocks);

        if (! empty($faq_items)) {
            $out[] = [
                '@type' => 'FAQPage',
                '@id' => (string) get_permalink($post_id).'#faq',
                'mainEntity' => $faq_items,
            ];
        }
        if (! empty($howto_steps)) {
            $out[] = [
                '@type' => 'HowTo',
                '@id' => (string) get_permalink($post_id).'#howto',
                'name' => (string) get_the_title($post_id),
                'step' => $howto_steps,
            ];
        }

        return $out;
    }
}
