<?php
/**
 * Auto-creates redirects when published posts change slug or are trashed.
 *
 *   - `post_updated` (before the slug actually lands in the DB) compares
 *     permalinks before/after and mints a 301 old→new if the post was
 *     published and the URL changed.
 *   - `transition_post_status` → trash: stores the last known permalink on
 *     the post so the admin page can offer a post-trash "where should this
 *     URL go?" modal. If the site uses "delete permanently" straight away we
 *     still have the breadcrumb from `before_delete_post`.
 *
 * Nothing here blocks the edit — if anything fails we log via
 * `error_log('EBQ auto-redirect: …')` and let the save proceed.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Redirects_Auto
{
    public function register(): void
    {
        add_action('post_updated', [$this, 'maybe_create_on_slug_change'], 10, 3);
        add_action('transition_post_status', [$this, 'handle_transition'], 10, 3);
        add_action('before_delete_post', [$this, 'remember_permalink_before_delete']);
    }

    public function maybe_create_on_slug_change(int $post_id, WP_Post $post_after, WP_Post $post_before): void
    {
        if ($post_after->post_status !== 'publish' || $post_before->post_status !== 'publish') {
            return;
        }
        if ($post_after->post_name === $post_before->post_name && $post_after->post_parent === $post_before->post_parent) {
            return;
        }

        $old_url = (string) get_permalink($post_before);
        $new_url = (string) get_permalink($post_after);
        if ($old_url === '' || $new_url === '' || $old_url === $new_url) {
            return;
        }

        $old_path = $this->to_path($old_url);
        $new_path = $this->to_path($new_url);
        if ($old_path === '' || $new_path === '' || $old_path === $new_path) {
            return;
        }

        $redirects = new EBQ_Redirects();
        if ($redirects->find_by_source($old_path) !== null) {
            return;
        }
        $saved = $redirects->upsert([
            'source' => $old_path,
            'target' => $new_path,
            'type' => EBQ_Redirects::TYPE_301,
            'regex' => false,
            'notes' => sprintf(
                /* translators: %d is a post ID */
                __('Auto-created when post #%d slug changed.', 'ebq-seo'),
                $post_id
            ),
        ]);

        if (! $saved) {
            error_log('EBQ auto-redirect: failed to create slug-change 301 for post '.$post_id);
        }
    }

    public function handle_transition(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($new_status !== 'trash' || $old_status !== 'publish') {
            return;
        }
        // We can't get_permalink() on a trashed post (returns the trash URL). Stash the last public URL.
        $last_url = (string) get_permalink($post->ID);
        // But transition fires AFTER status flip, so get_permalink already reflects trash. Recompute from post_name.
        if ($last_url === '' || strpos($last_url, '__trashed') !== false) {
            $slug = (string) str_replace('__trashed', '', $post->post_name);
            $last_url = (string) home_url('/'.ltrim($slug, '/'));
        }
        update_post_meta($post->ID, '_ebq_r_trashed_url', $last_url);
    }

    public function remember_permalink_before_delete(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type === EBQ_Redirects::CPT) {
            return;
        }
        $url = (string) get_post_meta($post_id, '_ebq_r_trashed_url', true);
        if ($url === '') {
            $url = (string) get_permalink($post);
        }
        if ($url === '') {
            return;
        }
        // Park in a transient keyed by post_id so the admin "trash" queue can still render.
        set_transient('ebq_r_last_url_'.$post_id, $url, WEEK_IN_SECONDS);
    }

    private function to_path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '';
        }

        return '/'.ltrim($path, '/');
    }
}
