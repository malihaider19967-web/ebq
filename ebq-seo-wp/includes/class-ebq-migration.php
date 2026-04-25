<?php
/**
 * Migration runner — pulls per-post SEO data from another plugin (Yoast,
 * Rank Math) into our `_ebq_*` post-meta surface. Mirrors the existing
 * redirects-importer pattern (admin_post_*, transient state) but for the
 * full per-post meta + schema + breadcrumb domain.
 *
 * Architecture: one `EBQ_Migration_Source` subclass per source plugin,
 * one shared `EBQ_Migration` runner that drives the batched WP-Cron loop
 * and persists progress in transients so a 50k-post site never blocks
 * the admin request.
 *
 * Conflict policy is intentionally fixed at "skip" — every per-meta write
 * is gated on `if (! get_post_meta($id, $key, true))`. Posts the user has
 * already edited in EBQ are untouched.
 */

if (! defined('ABSPATH')) {
    exit;
}

abstract class EBQ_Migration_Source
{
    /**
     * Stable, lowercase id used in transient keys, REST routes, options.
     * E.g. 'yoast', 'rankmath'.
     */
    abstract public function id(): string;

    /** Human-readable label for the UI ("Yoast SEO", "Rank Math"). */
    abstract public function label(): string;

    /**
     * True when this source has any data on this site OR its plugin is
     * active. Falls back to "is there any matching meta?" so users can
     * still migrate after uninstalling the source plugin.
     */
    abstract public function is_available(): bool;

    /** Posts that contain at least one key from this source. */
    abstract public function count_posts(): int;

    /**
     * @return list<int> post IDs in stable order (by ID asc) so we can
     *   page predictably with offset/limit and resume after interruption.
     */
    abstract public function post_ids(int $offset, int $limit): array;

    /**
     * Migrate one post. Returns:
     *   ['imported_keys' => list<string>, 'errors' => list<string>]
     *
     * Implementations MUST gate every write on the skip-policy helper
     * `EBQ_Migration::write_if_empty()` so we never trample EBQ data.
     */
    abstract public function migrate_post(WP_Post $post): array;

    /**
     * Dry-run version of migrate_post — returns what WOULD be imported
     * without writing anything. Used by the settings-card preview so
     * users can see exactly what they're about to commit per post.
     *
     * Shape:
     *   [
     *     'post_id'   => int,
     *     'post_title'=> string,
     *     'post_type' => string,
     *     'edit_url'  => string|null,
     *     'changes'   => list<array{
     *        key: string,         // EBQ meta key, e.g. '_ebq_title'
     *        label: string,       // human label, e.g. 'SEO title'
     *        summary: string,     // short value preview (truncated), e.g. 'My SEO Title'
     *        will_skip: bool,     // true when the EBQ key already has a value
     *     }>,
     *   ]
     */
    abstract public function preview_post(WP_Post $post): array;

    /**
     * Site-level data this source has that ISN'T per-post (and so
     * doesn't show up in the preview table). Today: just the redirects
     * count — the per-source redirect importer lives in its own class
     * (`EBQ_Redirects_Importer`) but we surface a "(N) redirects ready"
     * row in the preview header so users know about it. Default is no
     * site-level data; subclasses override.
     *
     * @return array{redirects?: int}
     */
    public function site_level_counts(): array
    {
        return [];
    }
}

final class EBQ_Migration
{
    public const CRON_HOOK = 'ebq_migration_run_batch';
    public const BATCH_SIZE = 25;
    /** Transient TTL — long enough to survive cron lag, short enough to expire if abandoned. */
    private const STATE_TTL = HOUR_IN_SECONDS * 12;

    public function register(): void
    {
        // Cron hook fires the next batch in a separate request so a single
        // run never holds the admin request open. The transient state
        // tells the next batch where to resume.
        add_action(self::CRON_HOOK, [self::class, 'cron_tick'], 10, 1);
    }

    /**
     * Build the source instance by id. Adding a new source = one new line.
     */
    public static function source(string $id): ?EBQ_Migration_Source
    {
        switch ($id) {
            case 'yoast':    return class_exists('EBQ_Migration_Yoast')    ? new EBQ_Migration_Yoast()    : null;
            case 'rankmath': return class_exists('EBQ_Migration_RankMath') ? new EBQ_Migration_RankMath() : null;
            default:         return null;
        }
    }

    /**
     * @return list<EBQ_Migration_Source>
     */
    public static function available_sources(): array
    {
        $out = [];
        foreach (['yoast', 'rankmath'] as $id) {
            $src = self::source($id);
            if ($src && $src->is_available()) {
                $out[] = $src;
            }
        }
        return $out;
    }

    /* ─── State (transient + completed-marker option) ──────────── */

    public static function state_key(string $source_id): string
    {
        return 'ebq_migration_state_' . $source_id;
    }

    /**
     * @return array{state: string, total: int, processed: int, imported_keys_total: int, errors: list<string>, started_at: ?int, finished_at: ?int}
     */
    public static function get_state(string $source_id): array
    {
        $stored = get_transient(self::state_key($source_id));
        if (! is_array($stored)) {
            $completed = (int) get_option('ebq_migration_completed_' . $source_id, 0);
            return [
                'state' => $completed ? 'completed' : 'idle',
                'total' => 0,
                'processed' => 0,
                'imported_keys_total' => 0,
                'errors' => [],
                'started_at' => null,
                'finished_at' => $completed > 0 ? $completed : null,
            ];
        }
        return $stored;
    }

    private static function set_state(string $source_id, array $state): void
    {
        set_transient(self::state_key($source_id), $state, self::STATE_TTL);
    }

    /* ─── Lifecycle ────────────────────────────────────────────── */

    /**
     * Kick off a new migration. Idempotent — if one is already running
     * for this source, returns its current state instead of restarting.
     */
    public static function start(string $source_id): array
    {
        $src = self::source($source_id);
        if (! $src) {
            return ['state' => 'error', 'message' => 'Unknown migration source: ' . $source_id];
        }

        $current = self::get_state($source_id);
        if (in_array($current['state'] ?? '', ['running', 'queued'], true)) {
            return $current;
        }

        $total = $src->count_posts();
        $state = [
            'state' => $total > 0 ? 'queued' : 'completed',
            'total' => $total,
            'processed' => 0,
            'imported_keys_total' => 0,
            'errors' => [],
            'started_at' => time(),
            'finished_at' => $total === 0 ? time() : null,
        ];
        self::set_state($source_id, $state);

        if ($total > 0) {
            // Schedule the first batch immediately. Subsequent batches
            // schedule themselves at the end of cron_tick().
            wp_schedule_single_event(time() + 1, self::CRON_HOOK, [$source_id]);
            // Also trigger spawn_cron so the user doesn't have to wait
            // for a real visitor to kick off the loop.
            spawn_cron();
        } else {
            update_option('ebq_migration_completed_' . $source_id, time(), false);
        }

        return $state;
    }

    public static function cancel(string $source_id): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK, [$source_id]);
        delete_transient(self::state_key($source_id));
    }

    /**
     * Cron callback. Processes one batch then re-schedules itself if
     * there's more work. Bails cleanly when state is missing (cancelled).
     */
    public static function cron_tick(string $source_id): void
    {
        $src = self::source($source_id);
        if (! $src) return;

        $state = self::get_state($source_id);
        if (! in_array($state['state'] ?? '', ['queued', 'running'], true)) {
            return; // cancelled or already finished
        }

        $state['state'] = 'running';
        self::set_state($source_id, $state);

        $offset = (int) ($state['processed'] ?? 0);
        $ids = $src->post_ids($offset, self::BATCH_SIZE);
        if (empty($ids)) {
            // Nothing left — mark complete.
            $state['state'] = 'completed';
            $state['finished_at'] = time();
            self::set_state($source_id, $state);
            update_option('ebq_migration_completed_' . $source_id, time(), false);
            return;
        }

        foreach ($ids as $id) {
            $post = get_post($id);
            if (! $post instanceof WP_Post) {
                $state['processed']++;
                continue;
            }
            try {
                $result = $src->migrate_post($post);
                $state['imported_keys_total'] += count($result['imported_keys'] ?? []);
                if (! empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        // Cap error log so a runaway migration doesn't
                        // blow the transient size.
                        if (count($state['errors']) < 50) {
                            $state['errors'][] = '[#' . $post->ID . '] ' . (string) $err;
                        }
                    }
                }
            } catch (\Throwable $e) {
                if (count($state['errors']) < 50) {
                    $state['errors'][] = '[#' . $post->ID . '] ' . $e->getMessage();
                }
            }
            $state['processed']++;
        }

        if ($state['processed'] >= $state['total']) {
            $state['state'] = 'completed';
            $state['finished_at'] = time();
            self::set_state($source_id, $state);
            update_option('ebq_migration_completed_' . $source_id, time(), false);
            return;
        }

        // More work — persist and reschedule.
        self::set_state($source_id, $state);
        wp_schedule_single_event(time() + 1, self::CRON_HOOK, [$source_id]);
    }

    /**
     * Lightweight summary for the preview panel — trim, strip tags, cap
     * length. Used by the source classes when building `changes`. Keep
     * it short so the table stays scannable on narrower screens.
     */
    public static function summarize($value, int $max = 80): string
    {
        if (is_array($value)) {
            // For lists/objects show a count instead of dumping JSON.
            return sprintf('%d item(s)', count($value));
        }
        if (is_bool($value)) {
            return $value ? 'yes' : '';
        }
        $s = wp_strip_all_tags((string) $value);
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max - 1) . '…';
        }
        return $s;
    }

    /**
     * True when an EBQ meta key currently has a non-empty value (so the
     * source-side change should be marked `will_skip`). Mirrors the gate
     * used in `write_if_empty()` so preview and migrate stay in sync.
     */
    public static function ebq_key_set(int $post_id, string $ebq_key): bool
    {
        $existing = get_post_meta($post_id, $ebq_key, true);
        return $existing !== '' && $existing !== null && $existing !== false && $existing !== '0';
    }

    /* ─── Per-meta write helper (the skip-policy gate) ─────────── */

    /**
     * Write `$value` to `$ebq_key` ONLY if the meta is currently empty
     * (or the special `_ebq_robots_*` boolean defaults). Returns true
     * if a write happened, false if we skipped.
     *
     * `$value` empty (`''`, `null`, `[]`) is also a no-op so we don't
     * burn a meta row on data we wouldn't display anyway.
     */
    public static function write_if_empty(int $post_id, string $ebq_key, $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        $existing = get_post_meta($post_id, $ebq_key, true);
        // Booleans saved as '1'/'' from our register_post_meta defaults —
        // treat '' as empty, '1' as set.
        if ($existing !== '' && $existing !== null && $existing !== false && $existing !== '0') {
            return false;
        }
        update_post_meta($post_id, $ebq_key, $value);
        return true;
    }
}
