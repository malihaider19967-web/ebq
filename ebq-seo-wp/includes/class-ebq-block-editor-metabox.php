<?php
/**
 * Yoast-style block-editor metabox: a normal/high postbox with
 * __block_editor_compatible_meta_box so the SEO UI sits in the meta boxes
 * region (see yoastreference/admin/metabox/class-metabox.php add_meta_box).
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Block_Editor_Seo_Metabox
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box'], 5);
    }

    public function add_meta_box(string $post_type): void
    {
        $supported = apply_filters('ebq_block_editor_seo_metabox_post_types', ['post', 'page']);
        if (! in_array($post_type, $supported, true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || ! method_exists($screen, 'is_block_editor') || ! $screen->is_block_editor()) {
            return;
        }

        add_filter("postbox_classes_{$post_type}_ebq-seo-editor", [$this, 'postbox_classes']);

        add_meta_box(
            'ebq-seo-editor',
            __('EBQ SEO', 'ebq-seo'),
            [$this, 'render'],
            $post_type,
            'normal',
            'high',
            [
                '__block_editor_compatible_meta_box' => true,
            ]
        );
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public function postbox_classes(array $classes): array
    {
        $classes[] = 'ebq-wpseo-metabox';

        return $classes;
    }

    public function render(): void
    {
        echo '<div id="ebq-seo-editor-root" class="ebq-seo-metabox-react-root"></div>';
    }
}
