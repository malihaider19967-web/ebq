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
        add_action('admin_post_ebq_migrate_start', [$this, 'handle_migrate_start']);
        add_action('admin_post_ebq_migrate_cancel', [$this, 'handle_migrate_cancel']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function handle_migrate_start(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_migrate_start');
        $source = isset($_POST['source']) ? sanitize_key((string) wp_unslash($_POST['source'])) : '';
        if ($source === '') {
            wp_safe_redirect(self::url('ebq_status=migrate_bad_source#ebq-migrate'));
            exit;
        }
        EBQ_Migration::start($source);
        wp_safe_redirect(self::url('ebq_status=migrate_started&source=' . $source . '#ebq-migrate'));
        exit;
    }

    public function handle_migrate_cancel(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'ebq-seo'), '', ['response' => 403]);
        }
        check_admin_referer('ebq_migrate_cancel');
        $source = isset($_POST['source']) ? sanitize_key((string) wp_unslash($_POST['source'])) : '';
        if ($source !== '') {
            EBQ_Migration::cancel($source);
        }
        wp_safe_redirect(self::url('ebq_status=migrate_cancelled#ebq-migrate'));
        exit;
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

            <?php $this->render_migrate_card(); ?>

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
                            esc_html_e('Standing down for another SEO plugin', 'ebq-seo');
                        } else {
                            esc_html_e('No conflicting SEO plugin detected', 'ebq-seo');
                        }
                        ?>
                    </h3>
                    <p class="ebq-card__lead">
                        <?php esc_html_e('When another major SEO plugin is active, EBQ stops emitting <title>, meta description, canonical, robots, OG, X cards, JSON-LD, and the XML sitemap. The editor sidebar, post list column, dashboard widget, redirects, and OAuth connect keep working.', 'ebq-seo'); ?>
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
     * "Migrate from another SEO plugin" card. Auto-detects Yoast / Rank
     * Math, shows per-source post counts and either an "Import" button
     * (idle) or a live progress block (queued/running) that polls the
     * status REST endpoint. Footer link sends users to the existing
     * redirects importer for the rest of the migration story.
     */
    private function render_migrate_card(): void
    {
        $sources = EBQ_Migration::available_sources();
        $redirects_url = admin_url('admin.php?page=' . EBQ_Redirects_Admin::MENU_SLUG);
        ?>
        <section class="ebq-card" id="ebq-migrate">
            <p class="ebq-card__eyebrow"><?php esc_html_e('Migrate from your previous SEO plugin', 'ebq-seo'); ?></p>
            <h3 class="ebq-card__title"><?php esc_html_e('Import existing SEO data', 'ebq-seo'); ?></h3>
            <p class="ebq-card__lead">
                <?php esc_html_e('We pull focus keyphrases, additional keyphrases, SEO titles, descriptions, canonical URLs, robots flags, social-card overrides, schemas, and breadcrumb labels into EBQ. Posts you have already configured in EBQ are never overwritten.', 'ebq-seo'); ?>
            </p>

            <?php if (empty($sources)): ?>
                <p class="ebq-help" style="margin:0;">
                    <?php esc_html_e('No data from another SEO plugin was detected on this site. If you used to run one and have already removed it, the import buttons will reappear if any of its meta keys still exist.', 'ebq-seo'); ?>
                </p>
            <?php else: ?>
                <div class="ebq-migrate-list" style="display:flex;flex-direction:column;gap:12px;margin-top:8px;">
                    <?php foreach ($sources as $source): $this->render_migrate_source_row($source); endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="ebq-help" style="margin-top:14px;">
                <?php
                printf(
                    /* translators: %s — link to the redirects admin page. */
                    esc_html__('Need to bring over your redirects too? Open the %s — it has a one-click import for the major SEO plugins.', 'ebq-seo'),
                    '<a href="' . esc_url($redirects_url) . '">' . esc_html__('redirects tool', 'ebq-seo') . '</a>'
                );
                ?>
            </p>
        </section>
        <?php
    }

    /**
     * One row of the migrate card per available source. Shows either the
     * idle "Preview / Import" buttons or, when a job is running, a progress
     * bar + cancel button + tally of processed/imported keys. When the
     * user has clicked "Preview", the per-post preview table is rendered
     * just below this row by `render_migrate_card()` itself.
     */
    private function render_migrate_source_row(EBQ_Migration_Source $source): void
    {
        $id = $source->id();
        $state = EBQ_Migration::get_state($id);
        $total = (int) ($state['total'] ?? 0);
        $processed = (int) ($state['processed'] ?? 0);
        $imported = (int) ($state['imported_keys_total'] ?? 0);
        $status = (string) ($state['state'] ?? 'idle');
        $count_for_label = $total > 0 ? $total : $source->count_posts();
        $site_level = $source->site_level_counts();
        $redirects_count = (int) ($site_level['redirects'] ?? 0);
        $redirects_url = admin_url('admin.php?page=' . EBQ_Redirects_Admin::MENU_SLUG);

        ?>
        <div class="ebq-migrate-row" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--ebq-border);border-radius:8px;background:var(--ebq-bg-subtle);flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <strong>
                    <?php
                    /* translators: %s — detected source plugin name (e.g. "Yoast SEO", "Rank Math") */
                    echo esc_html(sprintf(__('%s detected', 'ebq-seo'), $source->label()));
                    ?>
                </strong>
                <p class="ebq-help" style="margin:2px 0 0;">
                    <?php
                    if ($status === 'completed') {
                        echo esc_html(sprintf(
                            /* translators: 1 — count of meta keys, 2 — post count */
                            __('Imported %1$d EBQ keys across %2$d posts.', 'ebq-seo'),
                            $imported,
                            $processed
                        ));
                    } elseif ($status === 'running' || $status === 'queued') {
                        echo esc_html(sprintf(
                            /* translators: 1 — done, 2 — total */
                            __('Importing %1$d / %2$d posts…', 'ebq-seo'),
                            $processed,
                            $total
                        ));
                    } else {
                        echo esc_html(sprintf(
                            /* translators: %d — post count */
                            _n('%d post detected.', '%d posts detected.', $count_for_label, 'ebq-seo'),
                            $count_for_label
                        ));
                        if ($redirects_count > 0) {
                            echo ' · ';
                            printf(
                                /* translators: 1 — redirect count, 2 — link to the redirects tool */
                                esc_html__('%1$d redirects ready (use %2$s)', 'ebq-seo'),
                                $redirects_count,
                                '<a href="' . esc_url($redirects_url) . '">' . esc_html__('redirects tool', 'ebq-seo') . '</a>'
                            );
                        }
                    }
                    ?>
                </p>
                <?php if ($status === 'running' || $status === 'queued'): ?>
                    <?php $pct = $total > 0 ? min(100, max(2, (int) round(($processed / $total) * 100))) : 5; ?>
                    <div style="margin-top:6px;height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                        <div style="width:<?php echo (int) $pct; ?>%;height:100%;background:var(--ebq-accent);transition:width .4s ease;"></div>
                    </div>
                <?php endif; ?>
                <?php if (! empty($state['errors'])): ?>
                    <details style="margin-top:6px;">
                        <summary class="ebq-diag-summary"><?php
                            /* translators: %d — error count */
                            echo esc_html(sprintf(_n('%d issue', '%d issues', count($state['errors']), 'ebq-seo'), count($state['errors'])));
                        ?></summary>
                        <pre class="ebq-diag" style="max-height:160px;overflow:auto;"><?php echo esc_html(implode("\n", array_slice($state['errors'], 0, 25))); ?></pre>
                    </details>
                <?php endif; ?>
            </div>

            <div class="ebq-form-row" style="margin:0;">
                <?php if ($status === 'running' || $status === 'queued'): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="ebq_migrate_cancel">
                        <input type="hidden" name="source" value="<?php echo esc_attr($id); ?>">
                        <?php wp_nonce_field('ebq_migrate_cancel'); ?>
                        <button type="submit" class="ebq-btn ebq-btn--ghost"><?php esc_html_e('Cancel', 'ebq-seo'); ?></button>
                    </form>
                <?php else: ?>
                    <a href="<?php echo esc_url(self::url('ebq_preview=' . $id . '#ebq-migrate')); ?>" class="ebq-btn ebq-btn--ghost" <?php echo $count_for_label === 0 ? 'aria-disabled="true" style="pointer-events:none;opacity:.5;"' : ''; ?>>
                        <?php esc_html_e('Preview', 'ebq-seo'); ?>
                    </a>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="ebq_migrate_start">
                        <input type="hidden" name="source" value="<?php echo esc_attr($id); ?>">
                        <?php wp_nonce_field('ebq_migrate_start'); ?>
                        <button type="submit" class="ebq-btn ebq-btn--primary" <?php disabled($count_for_label, 0); ?>>
                            <?php
                            echo esc_html(
                                $status === 'completed'
                                    ? __('Re-run import', 'ebq-seo')
                                    : __('Import data', 'ebq-seo')
                            );
                            ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php

        // If the user clicked "Preview" for THIS source, render the
        // paginated per-post preview block immediately below the row.
        $preview_source = isset($_GET['ebq_preview']) ? sanitize_key((string) wp_unslash($_GET['ebq_preview'])) : '';
        if ($preview_source === $id && in_array($status, ['idle', 'completed'], true)) {
            $this->render_migrate_preview($source);
        }
    }

    /**
     * Per-post preview panel — paginated table showing exactly what
     * each post would gain from the import. Skipped fields (already set
     * in EBQ) render with a strikethrough so the user understands why
     * we won't touch them. Bottom of the panel has the same
     * "Import data" form so they can commit without scrolling back up.
     */
    private function render_migrate_preview(EBQ_Migration_Source $source): void
    {
        $id = $source->id();
        $per_page = 25;
        $page = max(1, (int) ($_GET['preview_page'] ?? 1));
        $total = $source->count_posts();
        $last_page = max(1, (int) ceil($total / $per_page));
        $offset = ($page - 1) * $per_page;
        $post_ids = $source->post_ids($offset, $per_page);

        ?>
        <div class="ebq-migrate-preview" style="border:1px solid var(--ebq-border);border-radius:8px;background:#fff;margin-top:-4px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border-bottom:1px solid var(--ebq-border);background:var(--ebq-bg-subtle);flex-wrap:wrap;">
                <strong style="font-size:13px;">
                    <?php
                    /* translators: 1 — source label, 2 — total post count */
                    echo esc_html(sprintf(__('Preview — %1$s data on %2$d posts', 'ebq-seo'), $source->label(), $total));
                    ?>
                </strong>
                <a href="<?php echo esc_url(self::url('#ebq-migrate')); ?>" class="ebq-btn ebq-btn--ghost ebq-btn--sm">
                    <?php esc_html_e('Close preview', 'ebq-seo'); ?>
                </a>
            </div>

            <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#eef2ff;border-bottom:1px solid var(--ebq-border);font-size:12px;color:#3730a3;">
                <span style="display:grid;place-items:center;width:18px;height:18px;border-radius:50%;background:#4f46e5;color:#fff;font-weight:700;font-size:11px;flex-shrink:0;font-style:italic;">i</span>
                <span>
                    <strong><?php esc_html_e('About schemas:', 'ebq-seo'); ?></strong>
                    <?php esc_html_e('EBQ auto-emits Article + WebPage + Organization JSON-LD on every post by default — same defaults as Yoast / Rank Math, so those graphs are unchanged after migration. The "Schemas (JSON-LD)" field below only appears for posts where you explicitly configured a custom type (FAQ, Recipe, Product, etc.) or added FAQ/HowTo blocks. Auto schemas are not "missing" — they\'re handled automatically.', 'ebq-seo'); ?>
                </span>
            </div>

            <table class="widefat striped" style="border:0;margin:0;">
                <thead>
                    <tr>
                        <th style="width:30%;"><?php esc_html_e('Post', 'ebq-seo'); ?></th>
                        <th><?php esc_html_e('Will import', 'ebq-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Buffer modals while iterating the table — we render them
                    // OUTSIDE the table afterwards (a <dialog> nested in a
                    // <tr> is invalid HTML and won't render reliably; on top
                    // of that the wrapping row was hidden, which also blocks
                    // showModal() from raising the element to the top layer).
                    $modals = [];
                    ?>
                    <?php if (empty($post_ids)): ?>
                        <tr><td colspan="2" style="text-align:center;color:var(--ebq-text-soft);padding:18px;">
                            <?php esc_html_e('No posts to preview.', 'ebq-seo'); ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($post_ids as $pid): ?>
                            <?php
                            $post = get_post($pid);
                            if (! $post instanceof WP_Post) continue;
                            $preview = $source->preview_post($post);
                            $changes = (array) $preview['changes'];
                            $will_write = array_filter($changes, static fn ($c) => empty($c['will_skip']));
                            $modal_id = 'ebq-mig-modal-' . $id . '-' . $pid;
                            if (! empty($changes)) {
                                $modals[$modal_id] = $preview;
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php if (! empty($preview['edit_url'])): ?>
                                            <a href="<?php echo esc_url($preview['edit_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($preview['post_title']); ?></a>
                                        <?php else: ?>
                                            <?php echo esc_html($preview['post_title']); ?>
                                        <?php endif; ?>
                                    </strong>
                                    <div class="ebq-help" style="margin-top:2px;">
                                        <?php echo esc_html('#' . $preview['post_id'] . ' · ' . $preview['post_type']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <span style="font-size:12px;color:var(--ebq-text);">
                                            <?php
                                            if (empty($changes)) {
                                                esc_html_e('No source data — nothing to import.', 'ebq-seo');
                                            } else {
                                                $write_n = count($will_write);
                                                $skip_n  = count($changes) - $write_n;
                                                if ($write_n > 0 && $skip_n === 0) {
                                                    echo esc_html(sprintf(_n('%d field will be imported.', '%d fields will be imported.', $write_n, 'ebq-seo'), $write_n));
                                                } elseif ($write_n === 0) {
                                                    esc_html_e('All fields already set in EBQ — nothing to do.', 'ebq-seo');
                                                } else {
                                                    /* translators: 1 — write count, 2 — skip count */
                                                    echo esc_html(sprintf(__('%1$d to import, %2$d skipped (already in EBQ).', 'ebq-seo'), $write_n, $skip_n));
                                                }
                                            }
                                            ?>
                                        </span>
                                        <?php if (! empty($changes)): ?>
                                            <button type="button" class="ebq-btn ebq-btn--ghost ebq-btn--sm" data-ebq-mig-open="<?php echo esc_attr($modal_id); ?>">
                                                <?php esc_html_e('View details', 'ebq-seo'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Modals rendered OUTSIDE the table so the <dialog> elements
            // are valid HTML and visible to showModal().
            foreach ($modals as $modal_id => $preview) {
                $this->render_migrate_post_modal($modal_id, $preview, $source);
            }
            ?>

            <?php if ($last_page > 1): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-top:1px solid var(--ebq-border);background:var(--ebq-bg-subtle);font-size:12px;">
                    <span style="color:var(--ebq-text-soft);">
                        <?php
                        /* translators: 1 — current page, 2 — total pages, 3 — total posts */
                        echo esc_html(sprintf(__('Page %1$d of %2$d · %3$d posts total', 'ebq-seo'), $page, $last_page, $total));
                        ?>
                    </span>
                    <span>
                        <?php if ($page > 1): ?>
                            <a class="ebq-btn ebq-btn--ghost ebq-btn--sm" href="<?php echo esc_url(self::url('ebq_preview=' . $id . '&preview_page=' . ($page - 1) . '#ebq-migrate')); ?>">← <?php esc_html_e('Prev', 'ebq-seo'); ?></a>
                        <?php endif; ?>
                        <?php if ($page < $last_page): ?>
                            <a class="ebq-btn ebq-btn--ghost ebq-btn--sm" href="<?php echo esc_url(self::url('ebq_preview=' . $id . '&preview_page=' . ($page + 1) . '#ebq-migrate')); ?>"><?php esc_html_e('Next', 'ebq-seo'); ?> →</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-top:1px solid var(--ebq-border);background:#fbfcfd;flex-wrap:wrap;">
                <span class="ebq-help" style="margin:0;">
                    <?php esc_html_e('Click "View details" on any row to inspect the source vs. EBQ values before importing.', 'ebq-seo'); ?>
                </span>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="ebq_migrate_start">
                    <input type="hidden" name="source" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('ebq_migrate_start'); ?>
                    <button type="submit" class="ebq-btn ebq-btn--primary"><?php esc_html_e('Start migration', 'ebq-seo'); ?> →</button>
                </form>
            </div>
        </div>

        <?php $this->render_migrate_modal_assets(); ?>
        <?php
    }

    /**
     * Per-post details modal. Rendered as a native <dialog> so we get
     * focus trap + ESC-to-close + backdrop for free, no JS framework.
     * Shows a side-by-side table:
     *
     *   Field | Source value | Action | Current EBQ value
     *
     * The "Action" column is the deciding column — green "+ Will import"
     * for fields that will land in EBQ, gray "Skipped" for fields the
     * user has already configured.
     */
    private function render_migrate_post_modal(string $modal_id, array $preview, EBQ_Migration_Source $source): void
    {
        $changes = (array) $preview['changes'];
        $post_id = (int) $preview['post_id'];
        ?>
        <dialog id="<?php echo esc_attr($modal_id); ?>" class="ebq-mig-modal">
                <article style="margin:0;padding:0;max-width:760px;width:96vw;">
                    <header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#f5f3ff,#fff);">
                        <div style="min-width:0;">
                            <strong style="display:block;font-size:14px;color:#0f172a;">
                                <?php
                                /* translators: 1 — source label, 2 — post title */
                                echo esc_html(sprintf(__('%1$s data on "%2$s"', 'ebq-seo'), $source->label(), $preview['post_title']));
                                ?>
                            </strong>
                            <span style="display:block;font-size:11px;color:#64748b;margin-top:2px;">
                                <?php echo esc_html('#' . $post_id . ' · ' . $preview['post_type']); ?>
                                <?php if (! empty($preview['edit_url'])): ?>
                                    · <a href="<?php echo esc_url($preview['edit_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open in editor', 'ebq-seo'); ?> ↗</a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <button type="button" class="ebq-mig-modal__close" data-ebq-mig-close style="background:0;border:0;font-size:24px;line-height:1;color:#64748b;cursor:pointer;width:30px;height:30px;border-radius:6px;">×</button>
                    </header>

                    <div style="padding:0;max-height:70vh;overflow:auto;">
                        <table class="widefat striped" style="border:0;margin:0;">
                            <thead>
                                <tr>
                                    <th style="width:25%;"><?php esc_html_e('Field', 'ebq-seo'); ?></th>
                                    <th style="width:30%;"><?php esc_html_e('Source value', 'ebq-seo'); ?></th>
                                    <th style="width:18%;"><?php esc_html_e('Action', 'ebq-seo'); ?></th>
                                    <th><?php esc_html_e('Current EBQ value', 'ebq-seo'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($changes as $c): ?>
                                    <?php
                                    $skip = ! empty($c['will_skip']);
                                    $existing = get_post_meta($post_id, $c['key'], true);
                                    $existing_summary = $existing !== '' && $existing !== null && $existing !== false
                                        ? EBQ_Migration::summarize($existing, 200)
                                        : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($c['label']); ?></strong>
                                            <div class="ebq-help" style="margin-top:2px;font-size:10px;color:#94a3b8;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">
                                                <?php echo esc_html($c['key']); ?>
                                            </div>
                                        </td>
                                        <td style="word-break:break-word;font-size:12px;">
                                            <?php echo esc_html(EBQ_Migration::summarize($c['summary'], 200)); ?>
                                        </td>
                                        <td>
                                            <?php if ($skip): ?>
                                                <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">
                                                    <?php esc_html_e('Skipped', 'ebq-seo'); ?>
                                                </span>
                                                <p class="ebq-help" style="margin:4px 0 0;"><?php esc_html_e('Already set in EBQ — left alone.', 'ebq-seo'); ?></p>
                                            <?php else: ?>
                                                <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">
                                                    + <?php esc_html_e('Import', 'ebq-seo'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="word-break:break-word;font-size:12px;">
                                            <?php if ($existing_summary !== ''): ?>
                                                <?php echo esc_html($existing_summary); ?>
                                            <?php else: ?>
                                                <span class="ebq-help"><?php esc_html_e('— empty —', 'ebq-seo'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <footer style="display:flex;justify-content:flex-end;padding:10px 18px;border-top:1px solid #e2e8f0;background:#fbfcfd;">
                        <button type="button" class="ebq-btn ebq-btn--ghost" data-ebq-mig-close><?php esc_html_e('Close', 'ebq-seo'); ?></button>
                    </footer>
                </article>
            </dialog>
        <?php
    }

    /**
     * One-time CSS + JS for the per-post modals. Idempotent — guarded
     * by a static flag so multi-source pages only emit once.
     */
    private function render_migrate_modal_assets(): void
    {
        static $emitted = false;
        if ($emitted) return;
        $emitted = true;
        ?>
        <style>
            dialog.ebq-mig-modal {
                padding: 0;
                border: 0;
                border-radius: 12px;
                box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
                background: #fff;
                color: #0f172a;
                max-width: 760px;
                width: 96vw;
            }
            dialog.ebq-mig-modal::backdrop {
                background: rgba(15, 23, 42, .55);
            }
            dialog.ebq-mig-modal table.widefat th {
                background: #fbfcfd;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: #475569;
            }
            .ebq-mig-modal__close:hover { background: #f1f5f9; color: #0f172a; }
        </style>
        <script>
        (function() {
            // Wire open/close once per page using event delegation so
            // pagination reloads don't double-bind.
            if (window.__ebqMigModalsBound) return;
            window.__ebqMigModalsBound = true;
            document.addEventListener('click', function (e) {
                var openBtn = e.target.closest('[data-ebq-mig-open]');
                if (openBtn) {
                    e.preventDefault();
                    var id = openBtn.getAttribute('data-ebq-mig-open');
                    var dlg = document.getElementById(id);
                    if (dlg && typeof dlg.showModal === 'function') {
                        dlg.showModal();
                    } else if (dlg) {
                        dlg.setAttribute('open', '');
                    }
                    return;
                }
                var closeBtn = e.target.closest('[data-ebq-mig-close]');
                if (closeBtn) {
                    e.preventDefault();
                    var dlg = closeBtn.closest('dialog');
                    if (dlg && typeof dlg.close === 'function') {
                        dlg.close();
                    } else if (dlg) {
                        dlg.removeAttribute('open');
                    }
                }
            });
            // Click on the backdrop closes the dialog. Native <dialog>
            // backdrop forwards clicks to the dialog element itself; if
            // the click coordinates land outside the visible panel, close.
            document.addEventListener('click', function (e) {
                if (e.target.tagName !== 'DIALOG' || !e.target.classList.contains('ebq-mig-modal')) return;
                var rect = e.target.getBoundingClientRect();
                var inDialog = e.clientY >= rect.top && e.clientY <= rect.bottom
                    && e.clientX >= rect.left && e.clientX <= rect.right;
                if (! inDialog) e.target.close();
            });
        })();
        </script>
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
            'migrate_started'   => ['good', __('Migration started — it runs in the background. Reload this page to see progress.', 'ebq-seo')],
            'migrate_cancelled' => ['warn', __('Migration cancelled.', 'ebq-seo')],
            'migrate_bad_source' => ['bad', __('Could not start migration — unknown source.', 'ebq-seo')],
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
