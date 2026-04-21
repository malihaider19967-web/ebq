<?php
/**
 * Redirects core — CPT registration, 404 interceptor, hit counter, data API.
 *
 * Storage: custom post type `ebq_redirect` with postmeta —
 *   _ebq_r_source     (string, path beginning with / or full URL for regex)
 *   _ebq_r_target     (string, path or absolute URL)
 *   _ebq_r_type       (int: 301 | 302 | 307 | 410)
 *   _ebq_r_regex      (bool, "1" if source is PCRE)
 *   _ebq_r_hits       (int, incremented on every served redirect)
 *   _ebq_r_last_hit   (Y-m-d H:i:s UTC)
 *   _ebq_r_notes      (free text)
 *
 * `post_title` mirrors the source path for searchability in the admin UI.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Redirects
{
    public const CPT = 'ebq_redirect';
    public const TYPE_301 = 301;
    public const TYPE_302 = 302;
    public const TYPE_307 = 307;
    public const TYPE_410 = 410;
    public const VALID_TYPES = [self::TYPE_301, self::TYPE_302, self::TYPE_307, self::TYPE_410];

    public function register(): void
    {
        add_action('init', [$this, 'register_cpt']);
        add_action('template_redirect', [$this, 'maybe_redirect'], 9);
    }

    public function register_cpt(): void
    {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => __('Redirects', 'ebq-seo'),
                'singular_name' => __('Redirect', 'ebq-seo'),
            ],
            'public' => false,
            'show_ui' => false, // we render a custom admin page
            'show_in_menu' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
        ]);
    }

    /**
     * Serves matching redirects before WP's 404 template would render.
     */
    public function maybe_redirect(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $request_path = $this->current_request_path();
        if ($request_path === '') {
            return;
        }

        $match = $this->find_match($request_path);
        if ($match === null) {
            return;
        }

        [$post_id, $target, $type] = $match;

        $this->record_hit($post_id);

        if ($type === self::TYPE_410) {
            status_header(410);
            nocache_headers();
            exit;
        }

        wp_redirect($target, (int) $type);
        exit;
    }

    private function current_request_path(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri === '') {
            return '';
        }
        $path = parse_url($uri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '';
        }

        return '/'.ltrim($path, '/');
    }

    /**
     * @return array{0:int,1:string,2:int}|null {post_id, target, type}
     */
    private function find_match(string $path): ?array
    {
        $all = $this->all_redirects();
        foreach ($all as $row) {
            $source = (string) $row['source'];
            $target = (string) $row['target'];
            $type = (int) $row['type'];

            if ($row['regex']) {
                $pattern = '#'.str_replace('#', '\\#', $source).'#i';
                if (@preg_match($pattern, '') === false) {
                    continue; // invalid regex — skip silently
                }
                if (preg_match($pattern, $path, $matches)) {
                    $resolved = preg_replace($pattern, $target, $path);
                    if (! is_string($resolved) || $resolved === '') {
                        continue;
                    }

                    return [(int) $row['id'], $resolved, $type];
                }
            } else {
                if ($this->paths_equal($source, $path)) {
                    return [(int) $row['id'], $target, $type];
                }
            }
        }

        return null;
    }

    private function paths_equal(string $a, string $b): bool
    {
        return rtrim(strtolower($a), '/') === rtrim(strtolower($b), '/');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all_redirects(): array
    {
        $cached = wp_cache_get('all', 'ebq_redirects');
        if (is_array($cached)) {
            return $cached;
        }

        $posts = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);

        $out = [];
        foreach ($posts as $post) {
            $source = (string) get_post_meta($post->ID, '_ebq_r_source', true);
            $target = (string) get_post_meta($post->ID, '_ebq_r_target', true);
            $type = (int) get_post_meta($post->ID, '_ebq_r_type', true);
            if ($source === '' || ($target === '' && $type !== self::TYPE_410)) {
                continue;
            }
            if (! in_array($type, self::VALID_TYPES, true)) {
                $type = self::TYPE_301;
            }
            $out[] = [
                'id' => (int) $post->ID,
                'source' => $source,
                'target' => $target,
                'type' => $type,
                'regex' => (bool) get_post_meta($post->ID, '_ebq_r_regex', true),
                'hits' => (int) get_post_meta($post->ID, '_ebq_r_hits', true),
                'last_hit' => (string) get_post_meta($post->ID, '_ebq_r_last_hit', true),
                'notes' => (string) get_post_meta($post->ID, '_ebq_r_notes', true),
            ];
        }

        wp_cache_set('all', $out, 'ebq_redirects', 5 * MINUTE_IN_SECONDS);

        return $out;
    }

    public function bust_cache(): void
    {
        wp_cache_delete('all', 'ebq_redirects');
    }

    private function record_hit(int $post_id): void
    {
        $hits = (int) get_post_meta($post_id, '_ebq_r_hits', true) + 1;
        update_post_meta($post_id, '_ebq_r_hits', $hits);
        update_post_meta($post_id, '_ebq_r_last_hit', gmdate('Y-m-d H:i:s'));
    }

    /**
     * Create or update a redirect row.
     *
     * @param  array<string, mixed>  $data
     * @return int Post ID (0 on failure)
     */
    public function upsert(array $data, ?int $post_id = null): int
    {
        $source = isset($data['source']) ? trim((string) $data['source']) : '';
        if ($source === '') {
            return 0;
        }
        if ($source[0] !== '/' && ! preg_match('#^https?://#i', $source) && empty($data['regex'])) {
            $source = '/'.ltrim($source, '/');
        }

        $target = isset($data['target']) ? trim((string) $data['target']) : '';
        $type = isset($data['type']) ? (int) $data['type'] : self::TYPE_301;
        if (! in_array($type, self::VALID_TYPES, true)) {
            $type = self::TYPE_301;
        }
        $regex = ! empty($data['regex']);
        $notes = isset($data['notes']) ? (string) $data['notes'] : '';

        $args = [
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'post_title' => $source,
        ];
        if ($post_id !== null && $post_id > 0) {
            $args['ID'] = $post_id;
            $saved = wp_update_post($args, true);
        } else {
            $saved = wp_insert_post($args, true);
        }
        if (is_wp_error($saved) || ! $saved) {
            return 0;
        }
        $saved = (int) $saved;

        update_post_meta($saved, '_ebq_r_source', $source);
        update_post_meta($saved, '_ebq_r_target', $target);
        update_post_meta($saved, '_ebq_r_type', $type);
        update_post_meta($saved, '_ebq_r_regex', $regex ? '1' : '');
        update_post_meta($saved, '_ebq_r_notes', sanitize_textarea_field($notes));

        if (! metadata_exists('post', $saved, '_ebq_r_hits')) {
            update_post_meta($saved, '_ebq_r_hits', 0);
        }

        $this->bust_cache();

        return $saved;
    }

    public function delete(int $post_id): bool
    {
        if ($post_id <= 0 || get_post_type($post_id) !== self::CPT) {
            return false;
        }
        $ok = (bool) wp_delete_post($post_id, true);
        if ($ok) {
            $this->bust_cache();
        }

        return $ok;
    }

    public function find_by_source(string $source): ?int
    {
        $source = '/'.ltrim(trim($source), '/');
        foreach ($this->all_redirects() as $row) {
            if (! $row['regex'] && $this->paths_equal((string) $row['source'], $source)) {
                return (int) $row['id'];
            }
        }

        return null;
    }
}
