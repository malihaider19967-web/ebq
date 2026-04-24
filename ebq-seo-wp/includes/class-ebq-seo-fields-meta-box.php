<?php
/**
 * Editable classic-style meta box that writes the SEO + social fields.
 *
 * The block editor's React SEO panel is the fancy UI — this is the reliable,
 * always-visible fallback that works everywhere (Elementor, classic editor,
 * custom admin UIs). Both surfaces persist into the same post-meta keys
 * registered by EBQ_Meta_Fields, so switching editors round-trips cleanly.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Seo_Fields_Meta_Box
{
    public const NONCE_ACTION = 'ebq_seo_fields_save';
    public const NONCE_FIELD = 'ebq_seo_fields_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save'], 10, 2);
    }

    public function add_meta_box(string $post_type): void
    {
        $supported = apply_filters('ebq_seo_fields_post_types', ['post', 'page']);
        if (! in_array($post_type, $supported, true)) {
            return;
        }
        add_meta_box(
            'ebq-seo-fields',
            __('EBQ SEO (metadata & social)', 'ebq-seo'),
            [$this, 'render'],
            $post_type,
            'normal',
            'high'
        );
    }

    public function render(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $g = static fn (string $k, $d = '') => EBQ_Meta_Fields::get($post->ID, $k, $d);
        ?>
        <style>
            .ebq-fields{display:grid;grid-template-columns:1fr;gap:14px;}
            .ebq-fields label{font-weight:600;font-size:12px;display:block;margin-bottom:4px;}
            .ebq-fields input[type=text],.ebq-fields input[type=url],.ebq-fields textarea{width:100%;}
            .ebq-fields textarea{min-height:64px;}
            .ebq-fields .ebq-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
            .ebq-fields .ebq-note{font-size:11px;color:#64748b;margin-top:4px;}
            .ebq-fields .ebq-section{border-top:1px solid #e2e8f0;padding-top:12px;margin-top:4px;}
            .ebq-fields .ebq-section:first-child{border-top:none;padding-top:0;margin-top:0;}
            .ebq-fields h3{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:0 0 8px;}
            .ebq-fields .ebq-char{font-size:10px;color:#64748b;float:right;}
        </style>
        <div class="ebq-fields">
            <div class="ebq-section">
                <h3><?php esc_html_e('Search', 'ebq-seo'); ?></h3>

                <div>
                    <label for="ebq_title"><?php esc_html_e('SEO title', 'ebq-seo'); ?></label>
                    <input type="text" id="ebq_title" name="ebq_title" maxlength="120"
                        value="<?php echo esc_attr((string) $g('_ebq_title')); ?>" />
                    <p class="ebq-note"><?php esc_html_e('Shown in Google as the blue link. Keep under ~60 characters. Variables:', 'ebq-seo'); ?>
                        <code>%%title%%</code> <code>%%sep%%</code> <code>%%sitename%%</code>
                    </p>
                </div>

                <div style="margin-top:12px;">
                    <label for="ebq_slug_hint"><?php esc_html_e('URL slug (SEO)', 'ebq-seo'); ?></label>
                    <input type="text" id="ebq_slug_hint" readonly class="large-text" style="background:#f8fafc;"
                        value="<?php echo esc_attr((string) $post->post_name); ?>" />
                    <p class="ebq-note"><?php esc_html_e('Edit the permalink slug in the post sidebar under Permalink.', 'ebq-seo'); ?></p>
                </div>

                <div style="margin-top:12px;">
                    <label for="ebq_description"><?php esc_html_e('Meta description', 'ebq-seo'); ?></label>
                    <textarea id="ebq_description" name="ebq_description" maxlength="320"><?php echo esc_textarea((string) $g('_ebq_description')); ?></textarea>
                    <p class="ebq-note"><?php esc_html_e('Shown under the title in SERPs. Target 140–160 characters.', 'ebq-seo'); ?></p>
                </div>

                <div style="margin-top:12px;">
                    <label for="ebq_canonical"><?php esc_html_e('Canonical URL', 'ebq-seo'); ?></label>
                    <input type="url" id="ebq_canonical" name="ebq_canonical"
                        placeholder="<?php echo esc_attr((string) get_permalink($post->ID)); ?>"
                        value="<?php echo esc_attr((string) $g('_ebq_canonical')); ?>" />
                    <p class="ebq-note"><?php esc_html_e('Leave blank to use this post\'s permalink.', 'ebq-seo'); ?></p>
                </div>

                <div class="ebq-row" style="margin-top:12px;">
                    <label style="font-weight:400;">
                        <input type="checkbox" name="ebq_robots_noindex" value="1" <?php checked((bool) $g('_ebq_robots_noindex', false)); ?> />
                        <strong><?php esc_html_e('noindex', 'ebq-seo'); ?></strong>
                        <span class="ebq-note"><?php esc_html_e('Tell search engines not to index this post.', 'ebq-seo'); ?></span>
                    </label>
                    <label style="font-weight:400;">
                        <input type="checkbox" name="ebq_robots_nofollow" value="1" <?php checked((bool) $g('_ebq_robots_nofollow', false)); ?> />
                        <strong><?php esc_html_e('nofollow', 'ebq-seo'); ?></strong>
                        <span class="ebq-note"><?php esc_html_e('Tell search engines not to follow links.', 'ebq-seo'); ?></span>
                    </label>
                </div>

                <div style="margin-top:12px;">
                    <label for="ebq_robots_advanced"><?php esc_html_e('Advanced robots (comma-separated)', 'ebq-seo'); ?></label>
                    <input type="text" id="ebq_robots_advanced" name="ebq_robots_advanced" class="large-text"
                        placeholder="noarchive, nosnippet"
                        value="<?php echo esc_attr((string) $g('_ebq_robots_advanced')); ?>" />
                    <p class="ebq-note"><?php esc_html_e('Appended to the robots meta after index/noindex and follow/nofollow.', 'ebq-seo'); ?></p>
                </div>

                <div style="margin-top:12px;">
                    <label for="ebq_focus_keyword"><?php esc_html_e('Focus keyword', 'ebq-seo'); ?></label>
                    <input type="text" id="ebq_focus_keyword" name="ebq_focus_keyword"
                        value="<?php echo esc_attr((string) $g('_ebq_focus_keyword')); ?>" />
                    <p class="ebq-note"><?php esc_html_e('Optional. In the block editor, this is a dropdown populated with your real GSC queries.', 'ebq-seo'); ?></p>
                </div>
            </div>

            <div class="ebq-section">
                <h3><?php esc_html_e('Social (Open Graph)', 'ebq-seo'); ?></h3>

                <div>
                    <label for="ebq_og_title"><?php esc_html_e('OG title', 'ebq-seo'); ?></label>
                    <input type="text" id="ebq_og_title" name="ebq_og_title"
                        value="<?php echo esc_attr((string) $g('_ebq_og_title')); ?>" />
                </div>
                <div style="margin-top:12px;">
                    <label for="ebq_og_description"><?php esc_html_e('OG description', 'ebq-seo'); ?></label>
                    <textarea id="ebq_og_description" name="ebq_og_description"><?php echo esc_textarea((string) $g('_ebq_og_description')); ?></textarea>
                </div>
                <div style="margin-top:12px;">
                    <label for="ebq_og_image"><?php esc_html_e('OG image URL', 'ebq-seo'); ?></label>
                    <input type="url" id="ebq_og_image" name="ebq_og_image"
                        value="<?php echo esc_attr((string) $g('_ebq_og_image')); ?>" />
                    <p class="ebq-note"><?php esc_html_e('Leave blank to use the featured image.', 'ebq-seo'); ?></p>
                </div>
            </div>

            <div class="ebq-section">
                <h3><?php esc_html_e('Twitter / X card', 'ebq-seo'); ?></h3>

                <div>
                    <label for="ebq_twitter_title"><?php esc_html_e('Twitter title', 'ebq-seo'); ?></label>
                    <input type="text" id="ebq_twitter_title" name="ebq_twitter_title"
                        value="<?php echo esc_attr((string) $g('_ebq_twitter_title')); ?>" />
                </div>
                <div style="margin-top:12px;">
                    <label for="ebq_twitter_description"><?php esc_html_e('Twitter description', 'ebq-seo'); ?></label>
                    <textarea id="ebq_twitter_description" name="ebq_twitter_description"><?php echo esc_textarea((string) $g('_ebq_twitter_description')); ?></textarea>
                </div>
                <div style="margin-top:12px;">
                    <label for="ebq_twitter_image"><?php esc_html_e('Twitter image URL', 'ebq-seo'); ?></label>
                    <input type="url" id="ebq_twitter_image" name="ebq_twitter_image"
                        value="<?php echo esc_attr((string) $g('_ebq_twitter_image')); ?>" />
                </div>
            </div>
        </div>
        <?php
    }

    public function save(int $post_id, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }
        if (! isset($_POST[self::NONCE_FIELD]) || ! wp_verify_nonce((string) $_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }

        $simple = [
            '_ebq_title' => 'ebq_title',
            '_ebq_description' => 'ebq_description',
            '_ebq_focus_keyword' => 'ebq_focus_keyword',
            '_ebq_og_title' => 'ebq_og_title',
            '_ebq_og_description' => 'ebq_og_description',
            '_ebq_twitter_title' => 'ebq_twitter_title',
            '_ebq_twitter_description' => 'ebq_twitter_description',
        ];
        $urls = [
            '_ebq_canonical' => 'ebq_canonical',
            '_ebq_og_image' => 'ebq_og_image',
            '_ebq_twitter_image' => 'ebq_twitter_image',
        ];
        $booleans = [
            '_ebq_robots_noindex' => 'ebq_robots_noindex',
            '_ebq_robots_nofollow' => 'ebq_robots_nofollow',
        ];

        foreach ($simple as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? sanitize_text_field((string) wp_unslash($_POST[$post_key])) : '';
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        foreach ($urls as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? esc_url_raw((string) wp_unslash($_POST[$post_key])) : '';
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        foreach ($booleans as $meta_key => $post_key) {
            $value = ! empty($_POST[$post_key]);
            update_post_meta($post_id, $meta_key, $value ? '1' : '');
        }

        $adv = isset($_POST['ebq_robots_advanced']) ? sanitize_text_field((string) wp_unslash($_POST['ebq_robots_advanced'])) : '';
        if ($adv === '') {
            delete_post_meta($post_id, '_ebq_robots_advanced');
        } else {
            update_post_meta($post_id, '_ebq_robots_advanced', mb_substr($adv, 0, 200));
        }

        unset($post);
    }
}
