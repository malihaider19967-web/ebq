<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single database-shard box in the fleet. Created by an admin (or the
 * `ebq:db-node` command), provisioned on Hetzner, and drained/destroyed once
 * its tenants/crawl-sites have been moved off. {@see \App\Support\ShardManager}
 * turns each active row into a live Laravel connection named {@see connectionName()}.
 *
 * Holds NO secrets — a node authenticates with the shared app DB credential the
 * web box already knows; the only fleet secret (HCLOUD_TOKEN) lives on the web box.
 *
 * @property string $id  ULID
 * @property string $name
 * @property ?int $hetzner_server_id
 * @property ?string $private_ip
 * @property ?string $public_ip
 * @property ?string $server_type
 * @property string $role  primary|tenant-shard|crawl-shard
 * @property string $status
 * @property ?string $db_name
 * @property bool $is_pinned
 * @property int $tenant_count
 * @property int $site_count
 * @property ?bool $is_healthy
 * @property ?Carbon $last_seen_at
 * @property ?string $last_error
 * @property ?Carbon $provisioned_at
 * @property ?Carbon $drain_started_at
 * @property ?array $labels
 */
class DbNode extends Model
{
    use HasUlids;

    public const ROLE_PRIMARY = 'primary';
    public const ROLE_TENANT = 'tenant-shard';
    public const ROLE_CRAWL = 'crawl-shard';

    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAINING = 'draining';
    public const STATUS_DELETING = 'deleting';
    public const STATUS_FAILED = 'failed';

    /** Statuses that are consuming a (billable) Hetzner server right now. */
    public const BILLABLE_STATUSES = [
        self::STATUS_PROVISIONING,
        self::STATUS_ACTIVE,
        self::STATUS_DRAINING,
    ];

    /** Statuses for which {@see ShardManager} should register a connection. */
    public const REGISTERABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DRAINING,
    ];

    protected $fillable = [
        'name',
        'hetzner_server_id',
        'private_ip',
        'public_ip',
        'server_type',
        'role',
        'status',
        'db_name',
        'is_pinned',
        'tenant_count',
        'site_count',
        'is_healthy',
        'last_seen_at',
        'last_error',
        'provisioned_at',
        'drain_started_at',
        'labels',
    ];

    protected function casts(): array
    {
        return [
            'hetzner_server_id' => 'integer',
            'is_pinned' => 'boolean',
            'tenant_count' => 'integer',
            'site_count' => 'integer',
            'is_healthy' => 'boolean',
            'last_seen_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'drain_started_at' => 'datetime',
            'labels' => 'array',
        ];
    }

    /** The Laravel connection name this node is registered under. */
    public function connectionName(): string
    {
        return self::connectionNameFor($this->id);
    }

    public static function connectionNameFor(string $id): string
    {
        return 'node:'.$id;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    /** Nodes that currently cost money (a Hetzner server exists for them). */
    public function scopeBillable(Builder $q): Builder
    {
        return $q->whereIn('status', self::BILLABLE_STATUSES);
    }

    /** Drain candidates: active, not pinned, and empty of placed data. */
    public function scopeDrainable(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where('is_pinned', false);
    }

    public function scopeTenantShards(Builder $q): Builder
    {
        return $q->where('role', self::ROLE_TENANT);
    }

    public function scopeCrawlShards(Builder $q): Builder
    {
        return $q->where('role', self::ROLE_CRAWL);
    }

    public function isEmpty(): bool
    {
        return $this->tenant_count === 0 && $this->site_count === 0;
    }

    /**
     * Recompute tenant_count / site_count for EVERY node from actual data, and
     * persist any that drifted. These columns are otherwise only bumped by
     * {@see \App\Services\Sharding\ShardMover} moves — organic signups and new
     * crawl-sites land on the primary via NULL db_node_id / crawl_node_id and so
     * never increment its counters, which is how the primary drifts. Run this on
     * the fleet page (and after moves) so the counts self-heal.
     *
     * tenant_count = distinct website owners whose effective node is this node;
     * site_count   = crawl_sites whose effective node is this node. NULL routes to
     * the pinned primary, so COALESCE(node_id, primaryId) gives the effective node.
     *
     * @return array<string,array{tenants:int,sites:int}> authoritative counts by node id
     */
    public static function reconcileCounts(): array
    {
        $primaryId = static::where('is_pinned', true)->value('id');
        if ($primaryId === null) {
            return static::all()->mapWithKeys(fn (self $n) => [$n->id => ['tenants' => $n->tenant_count, 'sites' => $n->site_count]])->all();
        }

        $tenants = Website::query()
            ->selectRaw('COALESCE(db_node_id, ?) AS node_id, COUNT(DISTINCT user_id) AS c', [$primaryId])
            ->groupBy('node_id')->pluck('c', 'node_id');
        $sites = CrawlSite::query()
            ->selectRaw('COALESCE(crawl_node_id, ?) AS node_id, COUNT(*) AS c', [$primaryId])
            ->groupBy('node_id')->pluck('c', 'node_id');

        $map = [];
        foreach (static::all() as $node) {
            $t = (int) ($tenants[$node->id] ?? 0);
            $s = (int) ($sites[$node->id] ?? 0);
            $map[$node->id] = ['tenants' => $t, 'sites' => $s];
            if ($node->tenant_count !== $t || $node->site_count !== $s) {
                $node->forceFill(['tenant_count' => $t, 'site_count' => $s])->saveQuietly();
            }
        }

        return $map;
    }

    public function ageMinutes(): int
    {
        return (int) ($this->provisioned_at?->diffInMinutes(now()) ?? $this->created_at?->diffInMinutes(now()) ?? 0);
    }

    public function isDrainOverdue(int $graceSeconds): bool
    {
        return $this->drain_started_at !== null
            && $this->drain_started_at->copy()->addSeconds($graceSeconds)->isPast();
    }
}
