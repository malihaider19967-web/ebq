<?php
/**
 * Open Graph + Twitter Card emitter. Falls back sensibly:
 *   og:title     → post _ebq_og_title  → post _ebq_title      → post_title → site_name
 *   og:description → _ebq_og_description → _ebq_description → excerpt → tagline
 *   og:image     → _ebq_og_image       → featured image      → site icon
 *
 * Twitter cards default to summary_large_image when an image is present.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Social_Output
{
    public function register(): void
    {
        if (EBQ_Meta_Output::another_seo_plugin_is_active()) {
            return;
        }
        add_action('wp_head', [$this, 'output'], 3);
    }

    public function output(): void
    {
        $ctx = $this->resolve_context();

        // Open Graph.
        $this->tag('property', 'og:site_name', (string) get_bloginfo('name'));
        $this->tag('property', 'og:locale', str_replace('_', '-', (string) get_locale()));
        $this->tag('property', 'og:type', is_singular('post') ? 'article' : 'website');
        $this->tag('property', 'og:title', $ctx['og_title']);
        $this->tag('property', 'og:description', $ctx['og_description']);
        $this->tag('property', 'og:url', $ctx['canonical']);
        if ($ctx['og_image'] !== '') {
            $this->tag('property', 'og:image', $ctx['og_image']);
        }

        if (is_singular('post') && $ctx['post_id'] > 0) {
            $published = (string) get_the_date('c', $ctx['post_id']);
            $modified = (string) get_the_modified_date('c', $ctx['post_id']);
            if ($published !== '') {
                $this->tag('property', 'article:published_time', $published);
            }
            if ($modified !== '') {
                $this->tag('property', 'article:modified_time', $modified);
            }
        }

        // Twitter.
        $card = $ctx['twitter_image'] !== '' ? 'summary_large_image' : 'summary';
        $this->tag('name', 'twitter:card', $card);
        $this->tag('name', 'twitter:title', $ctx['twitter_title']);
        $this->tag('name', 'twitter:description', $ctx['twitter_description']);
        if ($ctx['twitter_image'] !== '') {
            $this->tag('name', 'twitter:image', $ctx['twitter_image']);
        }
    }

    private function resolve_context(): array
    {
        $post_id = is_singular() ? (int) get_queried_object_id() : 0;

        $seo_title = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_title', '') : '';
        $fallback_title = $post_id > 0 ? (string) get_the_title($post_id) : (string) get_bloginfo('name');
        $og_title_override = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_og_title', '') : '';
        $og_title = $og_title_override !== '' ? $og_title_override : ($seo_title !== '' ? $seo_title : $fallback_title);

        $seo_desc = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_description', '') : '';
        $og_desc_override = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_og_description', '') : '';
        $og_description = $og_desc_override !== '' ? $og_desc_override : ($seo_desc !== '' ? $seo_desc : (string) get_bloginfo('description'));

        $og_image_override = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_og_image', '') : '';
        $og_image = $og_image_override;
        if ($og_image === '' && $post_id > 0 && has_post_thumbnail($post_id)) {
            $og_image = (string) get_the_post_thumbnail_url($post_id, 'full');
        }

        $tw_title_override = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_twitter_title', '') : '';
        $tw_desc_override = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_twitter_description', '') : '';
        $tw_image_override = $post_id > 0 ? (string) EBQ_Meta_Fields::get($post_id, '_ebq_twitter_image', '') : '';

        return [
            'post_id' => $post_id,
            'canonical' => $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/'),
            'og_title' => $og_title,
            'og_description' => $og_description,
            'og_image' => $og_image,
            'twitter_title' => $tw_title_override !== '' ? $tw_title_override : $og_title,
            'twitter_description' => $tw_desc_override !== '' ? $tw_desc_override : $og_description,
            'twitter_image' => $tw_image_override !== '' ? $tw_image_override : $og_image,
        ];
    }

    private function tag(string $attr, string $key, string $value): void
    {
        if ($value === '') {
            return;
        }
        printf(
            "<meta %s=\"%s\" content=\"%s\" />\n",
            esc_attr($attr),
            esc_attr($key),
            esc_attr($value)
        );
    }
}
