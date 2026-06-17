<?php

namespace App\Support;

use App\Models\DbNode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Registers a live Laravel database connection for every active shard node so
 * that `DB::connection("node:{id}")` (and the tenant/crawl-tier models routed
 * via {@see ShardContext}) just work. Called once at boot from
 * {@see \App\Providers\AppServiceProvider::boot()}.
 *
 * Each node's connection clones the `global` (central) connection config and
 * overrides only host + database, so credentials/charset/options stay uniform —
 * the same shared app DB user exists on every box (mirrors the `.env.worker`
 * model of the crawl fleet).
 *
 * Degrades gracefully: during early boot / `migrate` (before `db_nodes` exists)
 * or if the DB is unreachable, it registers nothing instead of throwing — so the
 * installer and the test suite are unaffected.
 */
class ShardManager
{
    /** Cache TTL (seconds) for the node list; busted on placement/move changes. */
    public const CACHE_TTL = 60;

    public const CACHE_KEY = 'shard:nodes:v1';

    public function register(): void
    {
        foreach ($this->nodeConnections() as $name => $config) {
            Config::set("database.connections.{$name}", $config);
        }
    }

    /**
     * @return array<string, array<string, mixed>> connection name => config
     */
    public function nodeConnections(): array
    {
        $nodes = $this->loadNodes();
        $out = [];

        foreach ($nodes as $node) {
            // Skip rows that can't yet serve traffic (no IP/db assigned).
            if (empty($node['private_ip']) || empty($node['db_name'])) {
                continue;
            }
            $labels = $node['labels'] ?? [];
            $port = is_array($labels) && isset($labels['port']) ? (int) $labels['port'] : null;
            $out[DbNode::connectionNameFor($node['id'])] = $this->buildConfig(
                (string) $node['private_ip'],
                (string) $node['db_name'],
                $port,
            );
        }

        return $out;
    }

    /** Connection config for a node: the central template + host/database/port override. */
    public function buildConfig(string $host, string $database, ?int $port = null): array
    {
        $base = Config::get('database.connections.global')
            ?? Config::get('database.connections.'.Config::get('database.default'));

        return array_merge($base, array_filter([
            'host' => $host,
            'database' => $database,
            'port' => $port,
        ], fn ($v) => $v !== null));
    }

    /**
     * Active/draining nodes as plain arrays (cached). Returns [] if the table is
     * absent or the DB is unreachable.
     *
     * @return array<int, array{id:string, private_ip:?string, db_name:?string}>
     */
    private function loadNodes(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
                if (! Schema::hasTable('db_nodes')) {
                    return [];
                }

                return DbNode::query()
                    ->whereIn('status', DbNode::REGISTERABLE_STATUSES)
                    ->get(['id', 'private_ip', 'db_name', 'labels'])
                    ->map(fn (DbNode $n): array => [
                        'id' => $n->id,
                        'private_ip' => $n->private_ip,
                        'db_name' => $n->db_name,
                        'labels' => $n->labels,
                    ])
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /** Drop the cached node list (call after provisioning/draining/moving). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
