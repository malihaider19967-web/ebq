<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-editable knobs for the crawl-worker autoscaler. Stored in the `Setting`
 * table (JSON, cache-backed) so an admin can tune the fleet live from
 * /admin/fleet without a deploy — same pattern as {@see KeywordProviderConfig}.
 *
 * Every getter clamps to a sane range so a bad admin value can't make the
 * autoscaler do something dangerous (e.g. provision 1000 boxes). The fixed infra
 * (Hetzner token, network, ssh key) lives in config/services.php, NOT here.
 */
class AutoscalerConfig
{
    private const KEY = 'autoscaler';

    /** @var array<string,mixed> defaults — also the shape of the admin form. */
    public const DEFAULTS = [
        'enabled' => false,            // master kill-switch; off until an operator turns it on
        'min_boxes' => 1,              // floor (includes the pinned permanent box)
        'max_boxes' => 2,              // ceiling — keep below the DB write knee
        'target_backlog_per_box' => 400, // desired = ceil(crawl-queue depth / this)
        'server_type' => 'cpx31',      // Hetzner server type for new boxes
        'snapshot_id' => null,         // the worker snapshot image id (set after building it)
        'scale_up_cooldown_s' => 180,  // wait this long after a provision before another
        'scale_down_idle_s' => 900,    // backlog must stay low this long before draining a box
        'min_box_lifetime_s' => 3300,  // never drain a box younger than ~55 min (hourly billing)
        'per_domain_rate' => 2,        // distributed fetch rate ceiling, req/sec/domain
    ];

    /** @var array<string,array{0:int,1:int}> [min,max] clamps for the integer knobs. */
    private const CLAMPS = [
        'min_boxes' => [1, 20],
        'max_boxes' => [1, 50],
        'target_backlog_per_box' => [50, 100000],
        'scale_up_cooldown_s' => [30, 3600],
        'scale_down_idle_s' => [60, 86400],
        'min_box_lifetime_s' => [0, 86400],
        'per_domain_rate' => [1, 100],
    ];

    /** @return array<string,mixed> the full, defaulted config blob. */
    public static function all(): array
    {
        $stored = Setting::get(self::KEY, []);
        $stored = is_array($stored) ? $stored : [];

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function enabled(): bool
    {
        return (bool) self::all()['enabled'];
    }

    public static function minBoxes(): int
    {
        return self::clampInt('min_boxes');
    }

    public static function maxBoxes(): int
    {
        // max must never drop below min, regardless of how they were saved.
        return max(self::clampInt('max_boxes'), self::minBoxes());
    }

    public static function targetBacklogPerBox(): int
    {
        return self::clampInt('target_backlog_per_box');
    }

    public static function serverType(): string
    {
        $v = (string) (self::all()['server_type'] ?? self::DEFAULTS['server_type']);

        return $v !== '' ? $v : self::DEFAULTS['server_type'];
    }

    /** Worker snapshot image id; falls back to the config/services value. */
    public static function snapshotId(): ?string
    {
        $v = self::all()['snapshot_id'];

        return $v !== null && $v !== '' ? (string) $v : (config('services.hetzner.image') ?: null);
    }

    public static function scaleUpCooldownSeconds(): int
    {
        return self::clampInt('scale_up_cooldown_s');
    }

    public static function scaleDownIdleSeconds(): int
    {
        return self::clampInt('scale_down_idle_s');
    }

    public static function minBoxLifetimeSeconds(): int
    {
        return self::clampInt('min_box_lifetime_s');
    }

    public static function perDomainRate(): int
    {
        return self::clampInt('per_domain_rate');
    }

    /** Persist a partial update from the admin form (only known keys; values clamped on read). */
    public static function update(array $values): void
    {
        $merged = self::all();
        foreach (self::DEFAULTS as $k => $_) {
            if (array_key_exists($k, $values)) {
                $merged[$k] = $values[$k];
            }
        }
        Setting::set(self::KEY, $merged);
    }

    private static function clampInt(string $key): int
    {
        $v = (int) (self::all()[$key] ?? self::DEFAULTS[$key]);
        [$min, $max] = self::CLAMPS[$key] ?? [PHP_INT_MIN, PHP_INT_MAX];

        return max($min, min($max, $v));
    }
}
