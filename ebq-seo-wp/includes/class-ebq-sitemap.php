<?php
/**
 * XML sitemap: /ebq-sitemap.xml index + /ebq-sitemap-{type}-{page}.xml subsitemaps.
 *
 *   - Posts + pages + public custom post types (200 per page).
 *   - Honors per-post `_ebq_robots_noindex` — noindex'd posts are excluded.
 *   - `lastmod` = post_modified_gmt.
 *   - Auto-adds <sitemap> hint to robots.txt via EBQ_Robots_Txt if installed.
 *
 * Taxonomy sitemaps: public taxonomies, one subsitemap per taxonomy (paged).
 * Filter `ebq_sitemap_taxonomies` to narrow which taxonomies are included.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Sitemap
{
    public const POSTS_PER_PAGE = 200;

    public function register(): void
    {
        add_action('init', [$this, 'add_rewrites']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'maybe_serve']);
        add_action('save_post', [$this, 'bump_modified'], 10, 3);

        // Tell WP's own /wp-sitemap.xml to stand down — we own the sitemap.
        add_filter('wp_sitemaps_enabled', '__return_false');

        // Expose in robots.txt via a filter.
        add_filter('robots_txt', [$this, 'robots_append'], 10, 2);
    }

    public function add_rewrites(): void
    {
        add_rewrite_rule('^ebq-sitemap\.xml$', 'index.php?ebq_sitemap=index', 'top');
        add_rewrite_rule('^ebq-sitemap-([a-z0-9_-]+)-(\d+)\.xml$', 'index.php?ebq_sitemap=$matches[1]&ebq_sitemap_page=$matches[2]', 'top');
        add_rewrite_rule('^ebq-sitemap-tax-([a-z0-9_-]+)-(\d+)\.xml$', 'index.php?ebq_tax_sitemap=$matches[1]&ebq_tax_sitemap_page=$matches[2]', 'top');
    }

    public function register_query_vars(array $vars): array
    {
        $vars[] = 'ebq_sitemap';
        $vars[] = 'ebq_sitemap_page';
        $vars[] = 'ebq_tax_sitemap';
        $vars[] = 'ebq_tax_sitemap_page';

        return $vars;
    }

    public function maybe_serve(): void
    {
        $tax = (string) get_query_var('ebq_tax_sitemap');
        if ($tax !== '') {
            nocache_headers();
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
            $page = max(1, (int) get_query_var('ebq_tax_sitemap_page'));
            $this->render_tax_urlset($tax, $page);
            exit;
        }

        $which = (string) get_query_var('ebq_sitemap');
        if ($which === '') {
            return;
        }

        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        if ($which === 'index') {
            $this->render_index();
        } else {
            $page = max(1, (int) get_query_var('ebq_sitemap_page'));
            $this->render_urlset($which, $page);
        }
        exit;
    }

    private function render_index(): void
    {
        $entries = [];
        foreach ($this->indexed_post_types() as $type) {
            $count = $this->count_for_type($type);
            if ($count === 0) {
                continue;
            }
            $pages = (int) ceil($count / self::POSTS_PER_PAGE);
            for ($p = 1; $p <= $pages; $p++) {
                $entries[] = [
                    'loc' => home_url("/ebq-sitemap-{$type}-{$p}.xml"),
                    'lastmod' => $this->latest_modified_for_type($type),
                ];
            }
        }

        foreach ($this->indexed_taxonomies() as $taxonomy) {
            $count = $this->count_terms_for_taxonomy($taxonomy);
            if ($count === 0) {
                continue;
            }
            $pages = (int) ceil($count / self::POSTS_PER_PAGE);
            for ($p = 1; $p <= $pages; $p++) {
                $entries[] = [
                    'loc' => home_url("/ebq-sitemap-tax-{$taxonomy}-{$p}.xml"),
                    'lastmod' => '',
                ];
            }
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($entries as $e) {
            echo "  <sitemap>\n";
            echo '    <loc>'.esc_url($e['loc'])."</loc>\n";
            if (! empty($e['lastmod'])) {
                echo '    <lastmod>'.esc_html($e['lastmod'])."</lastmod>\n";
            }
            echo "  </sitemap>\n";
        }
        echo "</sitemapindex>\n";
    }

    private function render_urlset(string $type, int $page): void
    {
        if (! in_array($type, $this->indexed_post_types(), true)) {
            status_header(404);

            return;
        }

        $posts = get_posts([
            'post_type' => $type,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
            'paged' => $page,
            'posts_per_page' => self::POSTS_PER_PAGE,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_ebq_robots_noindex', 'compare' => 'NOT EXISTS'],
                ['key' => '_ebq_robots_noindex', 'value' => '0', 'compare' => '='],
                ['key' => '_ebq_robots_noindex', 'value' => '', 'compare' => '='],
            ],
        ]);

        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
        foreach ($posts as $post) {
            $loc = (string) get_permalink($post);
            if ($loc === '') {
                continue;
            }
            $canonical = (string) get_post_meta($post->ID, '_ebq_canonical', true);
            if ($canonical !== '' && $canonical !== $loc) {
                continue; // canonicalized elsewhere — don't advertise it
            }
            echo "  <url>\n";
            echo '    <loc>'.esc_url($loc)."</loc>\n";
            echo '    <lastmod>'.esc_html(mysql2date('c', $post->post_modified_gmt, false))."</lastmod>\n";
            if (has_post_thumbnail($post->ID)) {
                $image = (string) get_the_post_thumbnail_url($post->ID, 'full');
                if ($image !== '') {
                    echo "    <image:image>\n";
                    echo '      <image:loc>'.esc_url($image)."</image:loc>\n";
                    echo "    </image:image>\n";
                }
            }
            echo "  </url>\n";
        }
        echo "</urlset>\n";
    }

    private function render_tax_urlset(string $taxonomy, int $page): void
    {
        if (! taxonomy_exists($taxonomy) || ! in_array($taxonomy, $this->indexed_taxonomies(), true)) {
            status_header(404);

            return;
        }

        $offset = ($page - 1) * self::POSTS_PER_PAGE;
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => self::POSTS_PER_PAGE,
            'offset' => $offset,
            'orderby' => 'count',
            'order' => 'DESC',
        ]);

        if (is_wp_error($terms)) {
            status_header(404);

            return;
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }
            $link = get_term_link($term);
            if (is_wp_error($link)) {
                continue;
            }
            $loc = (string) $link;
            echo "  <url>\n";
            echo '    <loc>'.esc_url($loc)."</loc>\n";
            echo "  </url>\n";
        }
        echo "</urlset>\n";
    }

    /**
     * @return list<string>
     */
    private function indexed_post_types(): array
    {
        $types = ['post', 'page'];
        foreach (get_post_types(['public' => true, '_builtin' => false], 'names') as $custom) {
            $types[] = $custom;
        }

        return array_values(array_unique($types));
    }

    /**
     * @return list<string>
     */
    private function indexed_taxonomies(): array
    {
        $taxonomies = array_keys(get_taxonomies([
            'public' => true,
        ], 'names'));

        return array_values(array_unique(apply_filters('ebq_sitemap_taxonomies', $taxonomies)));
    }

    private function count_terms_for_taxonomy(string $taxonomy): int
    {
        $n = wp_count_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ]);

        return is_wp_error($n) ? 0 : (int) $n;
    }

    private function count_for_type(string $type): int
    {
        $counts = wp_count_posts($type);

        return (int) ($counts->publish ?? 0);
    }

    private function latest_modified_for_type(string $type): string
    {
        global $wpdb;
        $latest = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $type
        ));

        return $latest !== '' ? mysql2date('c', $latest, false) : '';
    }

    public function bump_modified($post_id, $post = null, $update = false): void
    {
        unset($post, $update);
        // Placeholder hook point — useful if we add CDN cache invalidation later.
    }

    public function robots_append(string $output, bool $public): string
    {
        if ($public) {
            $output .= "\nSitemap: ".home_url('/ebq-sitemap.xml')."\n";
        }

        return $output;
    }
}
