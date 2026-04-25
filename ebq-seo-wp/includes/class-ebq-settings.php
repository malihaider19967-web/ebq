<?php
/**
 * Settings page — single Connect button (no fields, no token pasting), plus
 * an updates card, title-separator field, EBQ workspace URL override, and a
 * diagnostics block.
 *
 * The HTML uses the shared `.ebq-admin` design tokens from src/admin/admin.css
 * which is enqueued only on this page.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Settings
{
    // Settings page now lives as a sub-menu of the EBQ HQ top-level menu
    // (add_submenu_page parent='ebq-hq'), so the hook becomes
    // `ebq-hq_page_ebq-seo` instead of the old `settings_page_ebq-seo`.
    public const PAGE_HOOK = 'ebq-hq_page_ebq-seo';
    public const PAGE_SLUG = 'ebq-seo';

    public function register(): void
    {
        // Use a high priority so we register AFTER EBQ_Hq_Page::register_menu
        // adds the parent top-level menu — otherwise add_submenu_page can't
        // find the parent slug.
        add_action('admin_menu', [$this, 'add_menu'], 20);
        add_action('admin_post_ebq_save_seo_globals', [$this, 'save_seo_globals']);
        add_action('admin_post_ebq_clear_cache', [$this, 'clear_cache']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Settings page URL — used by every other class that needs to deep-link
     * here (connect flow, updater, sidebars, dashboard widget). One place
     * to change it again later.
     */
    public static function url(string $extra = ''): string
    {
        $base = admin_url('admin.php?page=' . self::PAGE_SLUG);
        return $extra === '' ? $base : ($base . '&' . ltrim($extra, '?&'));
    }

    public function clear_cache(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_clear_cache');

        $count = EBQ_Api_Client::clear_response_cache();

        wp_safe_redirect(self::url('ebq_status=cache_cleared&ebq_cache_count=' . $count));
        exit;
    }

    public function enqueue_assets(string $hook): void
    {
        // Match either the top-level-parented hook (new) or the legacy
        // settings hook in case anything still bookmarks the old URL.
        if ($hook !== self::PAGE_HOOK && $hook !== 'settings_page_ebq-seo') {
            return;
        }

        $css = EBQ_SEO_PATH.'build/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'ebq-seo-admin',
                EBQ_SEO_URL.'build/admin.css',
                [],
                (string) filemtime($css)
            );
        }
    }

    public function save_seo_globals(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change this.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_save_seo_globals');

        $sep = isset($_POST['ebq_title_sep']) ? sanitize_text_field((string) wp_unslash($_POST['ebq_title_sep'])) : '';
        if ($sep === '') {
            delete_option(EBQ_Title_Template::OPTION_SEP);
        } else {
            update_option(EBQ_Title_Template::OPTION_SEP, mb_substr($sep, 0, 12));
        }

        wp_safe_redirect(self::url('ebq_status=seo_globals_saved'));
        exit;
    }

    public function add_menu(): void
    {
        // Sub-menu of the top-level EBQ HQ menu. Label is just "Settings"
        // since the parent already says "EBQ HQ".
        add_submenu_page(
            EBQ_Hq_Page::SLUG,
            __('EBQ Settings', 'ebq-seo'),
            __('Settings', 'ebq-seo'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Fallback: if admin_init didn't catch the connect callback (security
        // plugin dropped the hook, etc.), close the loop here.
        $inline_result = ! empty($_GET['ebq_token']) ? EBQ_Connect::process_callback_inline() : null;

        $status        = isset($_GET['ebq_status']) ? sanitize_key((string) wp_unslash($_GET['ebq_status'])) : '';
        $update_status = isset($_GET['ebq_update']) ? sanitize_key((string) wp_unslash($_GET['ebq_update'])) : '';
        $latest_query  = isset($_GET['latest']) ? sanitize_text_field((string) wp_unslash($_GET['latest'])) : '';

        $connected   = EBQ_Plugin::is_configured();
        $domain      = (string) get_option('ebq_website_domain', '');
        $website_id  = (int)    get_option('ebq_website_id', 0);
        $last_error  = (string) get_option('ebq_last_connect_error', '');

        $updater       = class_exists('EBQ_Updater') ? new EBQ_Updater() : null;
        $update_meta   = $updater ? $updater->fetch_meta() : null;
        $latest        = is_array($update_meta) ? (string) ($update_meta['version'] ?? '') : '';
        $has_update    = $latest !== '' && version_compare($latest, EBQ_SEO_VERSION, '>');

        ?>
        <div class="wrap ebq-admin ebq-admin__page">

            <?php $this->render_hero(); ?>

            <?php $this->render_notice($status); ?>
            <?php $this->render_inline_callback($inline_result); ?>
            <?php $this->render_last_error_banner($connected, $last_error); ?>

            <?php $this->render_connect_card($connected, $domain); ?>

            <?php $this->render_updates_card($has_update, $latest, $update_status, $latest_query); ?>

            <?php $this->render_title_sep_card(); ?>

            <?php $this->render_coexistence_card(); ?>

            <?php $this->render_diagnostics_card($connected); ?>
        </div>
        <?php
        // Suppress unused-var lint by referencing $website_id once.
        unset($website_id);
    }

    private function render_hero(): void
    {
        ?>
        <header class="ebq-admin__hero">
            <div class="ebq-admin__hero-mark" aria-hidden>E</div>
            <div>
                <h1><?php esc_html_e('EBQ SEO', 'ebq-seo'); ?></h1>
                <p><?php esc_html_e('Real-data focus keywords, live competitor SERP, cannibalization-aware canonical, plus on-page SEO parity with Yoast — powered by your EBQ workspace.', 'ebq-seo'); ?></p>
            </div>
            <span class="ebq-admin__hero-version">v<?php echo esc_html(EBQ_SEO_VERSION); ?></span>
        </header>
        <?php
    }

    private function render_inline_callback(?array $inline_result): void
    {
        if (! $inline_result) {
            return;
        }
        $level = $inline_result['outcome'] === 'connected' ? 'good' : 'bad';
        ?>
        <div class="ebq-notice ebq-notice--<?php echo esc_attr($level); ?>">
            <span class="ebq-notice__icon"><?php echo $inline_result['outcome'] === 'connected' ? '✓' : '!'; ?></span>
            <div style="flex:1;min-width:0;">
                <strong><?php echo esc_html($inline_result['message']); ?></strong>
                <?php if ($inline_result['outcome'] === 'connected'): ?>
                    <span style="opacity:.8;">— <?php esc_html_e('Reload this page to see the connected view.', 'ebq-seo'); ?></span>
                <?php endif; ?>
                <details style="margin-top:6px;">
                    <summary class="ebq-diag-summary"><?php esc_html_e('Diagnostics', 'ebq-seo'); ?></summary>
                    <pre class="ebq-diag"><?php echo esc_html(wp_json_encode($inline_result['debug'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                </details>
            </div>
        </div>
        <?php
    }

    private function render_last_error_banner(bool $connected, string $last_error): void
    {
        if ($connected || $last_error === '') {
            return;
        }
        ?>
        <div class="ebq-notice ebq-notice--bad">
            <span class="ebq-notice__icon">!</span>
            <div>
                <strong><?php esc_html_e('Last connection attempt failed', 'ebq-seo'); ?></strong>
                <pre class="ebq-diag" style="background:rgba(255,255,255,.55);"><?php echo esc_html($last_error); ?></pre>
                <p style="margin:6px 0 0;font-size:11px;opacity:.85;">
                    <?php esc_html_e('Click "Connect to EBQ" again. If this keeps happening, check that your browser isn\'t blocking third-party cookies on the EBQ domain during the redirect.', 'ebq-seo'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    private function render_connect_card(bool $connected, string $domain): void
    {
        if ($connected) {
            ?>
            <section class="ebq-card ebq-card--accent">
                <div class="ebq-card-row">
                    <div>
                        <p class="ebq-card__eyebrow"><?php esc_html_e('Connected', 'ebq-seo'); ?></p>
                        <h2 class="ebq-card__title"><?php echo esc_html($domain ?: __('EBQ workspace', 'ebq-seo')); ?></h2>
                        <p class="ebq-card__lead">
                            <?php esc_html_e('Insights are live in the editor sidebar, post list, and dashboard widget.', 'ebq-seo'); ?>
                        </p>
                    </div>
                    <span class="ebq-pill ebq-pill--good"><span class="ebq-pill__dot"></span><?php esc_html_e('Live', 'ebq-seo'); ?></span>
                </div>
                <div class="ebq-form-row" style="margin-top:14px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . EBQ_Hq_Page::SLUG)); ?>" class="ebq-btn ebq-btn--primary">
                        <?php esc_html_e('Open EBQ HQ', 'ebq-seo'); ?>
                    </a>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebq_clear_cache'), 'ebq_clear_cache')); ?>" class="ebq-btn ebq-btn--ghost">
                        <?php esc_html_e('Refresh data', 'ebq-seo'); ?>
                    </a>
                    <a href="<?php echo esc_url(EBQ_Connect::disconnect_url()); ?>" class="ebq-btn ebq-btn--ghost">
                        <?php esc_html_e('Disconnect', 'ebq-seo'); ?>
                    </a>
                </div>
                <p class="ebq-help" style="margin:8px 0 0;">
                    <?php esc_html_e('Insights are cached for 5 minutes. If something looks stale, hit Refresh data to drop the cache.', 'ebq-seo'); ?>
                </p>
            </section>
            <?php
        } else {
            ?>
            <section class="ebq-card ebq-card--accent">
                <p class="ebq-card__eyebrow"><?php esc_html_e('Step 1 — Connect', 'ebq-seo'); ?></p>
                <h2 class="ebq-card__title"><?php esc_html_e('Connect this site to EBQ', 'ebq-seo'); ?></h2>
                <p class="ebq-card__lead">
                    <?php esc_html_e('One click. You\'ll log in to EBQ, pick which website to link, and come back. The token is exchanged for you — nothing to copy or paste.', 'ebq-seo'); ?>
                </p>
                <div class="ebq-form-row">
                    <a href="<?php echo esc_url(EBQ_Connect::start_url()); ?>" class="ebq-btn ebq-btn--primary ebq-btn--lg">
                        <?php esc_html_e('Connect to EBQ', 'ebq-seo'); ?> →
                    </a>
                    <a href="<?php echo esc_url(EBQ_Api_Client::base_url() . '/register'); ?>" class="ebq-btn ebq-btn--ghost" target="_blank" rel="noopener">
                        <?php esc_html_e('Create a free account', 'ebq-seo'); ?>
                    </a>
                </div>
            </section>
            <?php
        }
    }

    private function render_updates_card(bool $has_update, string $latest, string $update_status, string $latest_query): void
    {
        ?>
        <section class="ebq-card">
            <div class="ebq-card-row">
                <div>
                    <p class="ebq-card__eyebrow"><?php esc_html_e('Plugin version', 'ebq-seo'); ?></p>
                    <?php if ($has_update): ?>
                        <h3 class="ebq-card__title" style="font-size:15px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span><?php echo esc_html(EBQ_SEO_VERSION); ?></span>
                            <span style="color:var(--ebq-text-soft);">→</span>
                            <span style="color:var(--ebq-good);">v<?php echo esc_html($latest); ?></span>
                            <span class="ebq-pill ebq-pill--good"><?php esc_html_e('Update available', 'ebq-seo'); ?></span>
                        </h3>
                    <?php else: ?>
                        <h3 class="ebq-card__title" style="font-size:15px;">
                            <?php echo esc_html(EBQ_SEO_VERSION); ?>
                            <?php if ($latest !== ''): ?>
                                <span class="ebq-pill"><?php esc_html_e('Latest', 'ebq-seo'); ?></span>
                            <?php endif; ?>
                        </h3>
                    <?php endif; ?>
                </div>
                <div class="ebq-form-row">
                    <?php if ($has_update): ?>
                        <a class="ebq-btn ebq-btn--primary" href="<?php echo esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin='.rawurlencode(plugin_basename(EBQ_SEO_FILE))), 'upgrade-plugin_'.plugin_basename(EBQ_SEO_FILE))); ?>">
                            <?php echo esc_html(sprintf(__('Install v%s', 'ebq-seo'), $latest)); ?>
                        </a>
                    <?php endif; ?>
                    <a class="ebq-btn ebq-btn--ghost" href="<?php echo esc_url(EBQ_Updater::check_url()); ?>">
                        <?php esc_html_e('Check for updates', 'ebq-seo'); ?>
                    </a>
                </div>
            </div>
            <?php if ($update_status === 'up_to_date'): ?>
                <p style="margin:8px 0 0;color:var(--ebq-good-text);font-size:12px;">✓ <?php esc_html_e('You\'re running the latest version.', 'ebq-seo'); ?></p>
            <?php elseif ($update_status === 'update_available'): ?>
                <p style="margin:8px 0 0;color:var(--ebq-good-text);font-size:12px;">
                    <?php echo esc_html(sprintf(__('Update v%s found.', 'ebq-seo'), $latest_query)); ?>
                </p>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_title_sep_card(): void
    {
        $sep_value = (string) (get_option(EBQ_Title_Template::OPTION_SEP, '') ?: EBQ_Title_Template::default_sep());
        ?>
        <section class="ebq-card">
            <p class="ebq-card__eyebrow"><?php esc_html_e('SEO title separator', 'ebq-seo'); ?></p>
            <h3 class="ebq-card__title"><?php esc_html_e('Title divider', 'ebq-seo'); ?></h3>
            <p class="ebq-card__lead">
                <?php esc_html_e('Used between %%title%% and %%sitename%% in SEO titles, both in the Gutenberg sidebar and the front-end <head>.', 'ebq-seo'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ebq-form-row">
                <input type="hidden" name="action" value="ebq_save_seo_globals">
                <?php wp_nonce_field('ebq_save_seo_globals'); ?>
                <input type="text" name="ebq_title_sep" maxlength="12" value="<?php echo esc_attr($sep_value); ?>" class="ebq-input" style="max-width:120px;" />
                <button type="submit" class="ebq-btn ebq-btn--ghost"><?php esc_html_e('Save', 'ebq-seo'); ?></button>
            </form>
        </section>
        <?php
    }

    private function render_coexistence_card(): void
    {
        $other = $this->detect_other_seo_plugin();
        ?>
        <section class="ebq-card">
            <p class="ebq-card__eyebrow"><?php esc_html_e('Coexistence mode', 'ebq-seo'); ?></p>
            <div class="ebq-card-row">
                <div>
                    <h3 class="ebq-card__title">
                        <?php
                        if ($other) {
                            echo esc_html(sprintf(__('Standing down for %s', 'ebq-seo'), $other));
                        } else {
                            esc_html_e('No conflicting SEO plugin detected', 'ebq-seo');
                        }
                        ?>
                    </h3>
                    <p class="ebq-card__lead">
                        <?php esc_html_e('When Yoast, Rank Math, AIOSEO, or SEO Framework is active, EBQ stops emitting <title>, meta description, canonical, robots, OG, X cards, JSON-LD, and the XML sitemap. The editor sidebar, post list column, dashboard widget, redirects, and OAuth connect keep working.', 'ebq-seo'); ?>
                    </p>
                </div>
                <span class="ebq-pill <?php echo $other ? 'ebq-pill--warn' : 'ebq-pill--good'; ?>">
                    <span class="ebq-pill__dot"></span>
                    <?php echo $other ? esc_html__('Coexisting', 'ebq-seo') : esc_html__('Sole SEO', 'ebq-seo'); ?>
                </span>
            </div>
        </section>
        <?php
    }

    /**
     * Diagnostics block — kept for support purposes but stripped of internal
     * plumbing (no website ID, no token chars, no API base, no workspace URL).
     * Just runtime info that helps reproduce a problem: WP/PHP/plugin
     * versions, multisite flag, object-cache presence, connection status.
     */
    private function render_diagnostics_card(bool $connected): void
    {
        $diag = [
            'plugin_version'      => EBQ_SEO_VERSION,
            'wp_version'          => get_bloginfo('version'),
            'php_version'         => PHP_VERSION,
            'is_multisite'        => is_multisite(),
            'object_cache_active' => wp_using_ext_object_cache(),
            'connected'           => $connected,
        ];
        ?>
        <section class="ebq-card">
            <details <?php echo $connected ? '' : 'open'; ?>>
                <summary class="ebq-diag-summary"><?php esc_html_e('Diagnostics (share when reporting issues)', 'ebq-seo'); ?></summary>
                <pre class="ebq-diag"><?php echo esc_html(wp_json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            </details>
        </section>
        <?php
    }

    private function detect_other_seo_plugin(): ?string
    {
        if (defined('WPSEO_VERSION'))         return 'Yoast SEO';
        if (defined('RANK_MATH_VERSION'))     return 'Rank Math';
        if (defined('AIOSEO_VERSION'))        return 'All in One SEO';
        if (class_exists('The_SEO_Framework\\Load')) return 'The SEO Framework';

        return null;
    }

    private function render_notice(string $status): void
    {
        if ($status === '') {
            return;
        }
        $count = isset($_GET['ebq_cache_count']) ? (int) $_GET['ebq_cache_count'] : 0;
        $map = [
            'connected'         => ['good', __('Connected successfully. Insights are now live.', 'ebq-seo')],
            'disconnected'      => ['warn', __('Disconnected. Local token cleared.', 'ebq-seo')],
            'state_mismatch'    => ['bad',  __('Connection rejected — the returned state did not match what this site issued. Try again.', 'ebq-seo')],
            'bad_token'         => ['bad',  __('EBQ sent back an empty or invalid token. Try again.', 'ebq-seo')],
            'seo_globals_saved' => ['good', __('SEO title settings saved.', 'ebq-seo')],
            'cache_cleared'     => ['good', sprintf(_n('Cleared %d cached EBQ response. Reload the editor to see fresh data.', 'Cleared %d cached EBQ responses. Reload the editor to see fresh data.', $count, 'ebq-seo'), $count)],
        ];
        if (! isset($map[$status])) {
            return;
        }
        [$tone, $message] = $map[$status];
        $icon = $tone === 'good' ? '✓' : ($tone === 'warn' ? '!' : '×');
        printf(
            '<div class="ebq-notice ebq-notice--%s"><span class="ebq-notice__icon">%s</span><span>%s</span></div>',
            esc_attr($tone),
            esc_html($icon),
            esc_html($message)
        );
    }
}
