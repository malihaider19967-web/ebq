<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-editable knobs for the database-node fleet — the DbNode equivalent of
 * {@see AutoscalerConfig}. Stored in the `Setting` table (JSON, cache-backed) so
 * an admin can manage it live from /admin/db-fleet without a deploy. Fixed infra
 * (Hetzner token/network/ssh key/db firewall/snapshot) lives in
 * config/services.php under `hetzner.*`, NOT here.
 *
 * Unlike the crawl-worker autoscaler there is NO automatic scaling loop: DB nodes
 * are provisioned/retired deliberately by an operator (data has to be migrated
 * onto/off them), so this only carries provisioning defaults + placement policy.
 */
class DbFleetConfig
{
    private const KEY = 'db_fleet';

    /** @var array<string,mixed> defaults — also the shape of the admin form. */
    public const DEFAULTS = [
        'server_type' => 'cx23',       // Hetzner type for new DB boxes
        'snapshot_id' => null,         // a MariaDB-preinstalled snapshot id
        'placement' => 'least_loaded', // least_loaded | round_robin — how new tenants pick a node
        'max_tenants_per_node' => 200, // soft cap surfaced in the UI for tenant-shard nodes
        'max_sites_per_node' => 2000,  // soft cap for crawl-shard nodes
    ];

    /** @var array<string,array{0:int,1:int}> [min,max] clamps for integer knobs. */
    private const CLAMPS = [
        'max_tenants_per_node' => [1, 100000],
        'max_sites_per_node' => [1, 1000000],
    ];

    /** @return array<string,mixed> the full, defaulted config blob. */
    public static function all(): array
    {
        $stored = Setting::get(self::KEY, []);
        $stored = is_array($stored) ? $stored : [];

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function serverType(): string
    {
        $v = (string) (self::all()['server_type'] ?? self::DEFAULTS['server_type']);

        return $v !== '' ? $v : self::DEFAULTS['server_type'];
    }

    /** DB snapshot image id; falls back to the config/services `hetzner.db_image`. */
    public static function snapshotId(): ?string
    {
        $v = self::all()['snapshot_id'];

        return $v !== null && $v !== '' ? (string) $v : (config('services.hetzner.db_image') ?: null);
    }

    public static function placement(): string
    {
        $v = (string) (self::all()['placement'] ?? 'least_loaded');

        return in_array($v, ['least_loaded', 'round_robin'], true) ? $v : 'least_loaded';
    }

    public static function maxTenantsPerNode(): int
    {
        return self::clampInt('max_tenants_per_node');
    }

    public static function maxSitesPerNode(): int
    {
        return self::clampInt('max_sites_per_node');
    }

    /** Persist a partial update from the admin form (only known keys). */
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
