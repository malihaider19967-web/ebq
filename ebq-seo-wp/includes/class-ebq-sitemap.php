<?php
/**
 * XML sitemap: /ebq-sitemap.xml index + /ebq-sitemap-{type}-{page}.xml subsitemaps.
 *
 *   - Posts + pages + public custom post types (200 per page).
 *   - Honors per-post `_ebq_robots_noindex` — noindex'd posts are excluded.
 *   - `lastmod` = post_modified_gmt.
 *   - Auto-adds <sitemap> hint to robots.txt via EBQ_Robots_Txt if installed.
 *
 * Taxonomy sitemaps are intentionally deferred to a follow-up — they rarely
 * move the needle vs post sitemaps and add meaningful complexity. If a user
 * wants them we can add a filter-extension point.
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
    }

    public function register_query_vars(array $vars): array
    {
        $vars[] = 'ebq_sitemap';
        $vars[] = 'ebq_sitemap_page';

        return $vars;
    }

    public function maybe_serve(): void
    {
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
