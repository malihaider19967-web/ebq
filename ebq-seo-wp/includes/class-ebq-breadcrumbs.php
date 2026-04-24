<?php
/**
 * Visible breadcrumb trail + shortcode. JSON-LD BreadcrumbList is already
 * emitted by EBQ_Schema_Output; this class is for theme output.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Breadcrumbs
{
    public function register(): void
    {
        add_shortcode('ebq_breadcrumbs', [$this, 'shortcode']);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<array{label: string, url: string}>
     */
    public static function get_items(int $post_id = 0): array
    {
        if ($post_id <= 0) {
            $post_id = is_singular() ? (int) get_queried_object_id() : 0;
        }
        $items = [];
        $items[] = [
            'label' => __('Home', 'ebq-seo'),
            'url' => home_url('/'),
        ];

        if ($post_id <= 0) {
            if (is_category() || is_tag() || is_tax()) {
                $term = get_queried_object();
                if ($term instanceof WP_Term) {
                    $items[] = [
                        'label' => (string) $term->name,
                        'url' => (string) get_term_link($term),
                    ];
                }
            }

            return $items;
        }

        foreach (array_reverse((array) get_post_ancestors($post_id)) as $ancestor_id) {
            $items[] = [
                'label' => (string) get_the_title($ancestor_id),
                'url' => (string) get_permalink($ancestor_id),
            ];
        }

        $items[] = [
            'label' => (string) get_the_title($post_id),
            'url' => (string) get_permalink($post_id),
        ];

        return $items;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public static function render_html(array $args = []): string
    {
        $post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
        $separator = isset($args['separator']) ? (string) $args['separator'] : ' › ';
        $class = isset($args['class']) ? sanitize_html_class((string) $args['class']) : 'ebq-breadcrumbs';

        $items = self::get_items($post_id);
        if (count($items) < 2) {
            return '';
        }

        $parts = [];
        $last = count($items) - 1;
        foreach ($items as $i => $row) {
            $label = esc_html($row['label']);
            if ($i < $last) {
                $parts[] = '<a href="'.esc_url($row['url']).'">'.$label.'</a>';
            } else {
                $parts[] = '<span aria-current="page">'.$label.'</span>';
            }
        }

        return '<nav class="'.esc_attr($class).'" aria-label="'.esc_attr__('Breadcrumb', 'ebq-seo').'">'
            .implode('<span class="ebq-bc-sep">'.esc_html($separator).'</span>', $parts)
            .'</nav>';
    }

    /**
     * @param  array<string, string>  $atts
     */
    public function shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'separator' => ' › ',
            'class' => 'ebq-breadcrumbs',
            'post_id' => '0',
        ], $atts, 'ebq_breadcrumbs');

        return self::render_html([
            'separator' => (string) $atts['separator'],
            'class' => (string) $atts['class'],
            'post_id' => (int) $atts['post_id'],
        ]);
    }
}
