<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * A single crawl-worker box in the fleet. Created by the autoscaler (or the
 * `ebq:fleet-worker` command), provisioned on Hetzner, and drained/destroyed on
 * scale-down. Health columns are refreshed by {@see \App\Console\Commands\CheckWorkerNodes}.
 *
 * Holds NO secrets — a node authenticates to Redis/MySQL with the .env it fetches
 * at boot; the fleet's only secret (HCLOUD_TOKEN) lives on the web box.
 *
 * @property string $name
 * @property ?int $hetzner_server_id
 * @property ?string $private_ip
 * @property ?string $public_ip
 * @property ?string $server_type
 * @property string $status
 * @property int $containers
 * @property bool $is_pinned
 * @property ?bool $is_healthy
 * @property ?Carbon $last_seen_at
 * @property ?int $last_queue_waiting
 * @property ?int $last_queue_running
 * @property ?string $last_error
 * @property ?Carbon $provisioned_at
 * @property ?Carbon $drain_started_at
 * @property ?array $labels
 */
class WorkerNode extends Model
{
    use HasUlids;
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

    protected $fillable = [
        'name',
        'hetzner_server_id',
        'private_ip',
        'public_ip',
        'server_type',
        'status',
        'containers',
        'is_pinned',
        'is_healthy',
        'last_seen_at',
        'last_queue_waiting',
        'last_queue_running',
        'last_error',
        'provisioned_at',
        'drain_started_at',
        'labels',
    ];

    protected function casts(): array
    {
        return [
            'hetzner_server_id' => 'integer',
            'containers' => 'integer',
            'is_pinned' => 'boolean',
            'is_healthy' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_queue_waiting' => 'integer',
            'last_queue_running' => 'integer',
            'provisioned_at' => 'datetime',
            'drain_started_at' => 'datetime',
            'labels' => 'array',
        ];
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

    /** Scale-down candidates: active, not pinned. Youngest first (already paid least). */
    public function scopeDrainable(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where('is_pinned', false)
            ->orderByDesc('provisioned_at');
    }

    public function ageMinutes(): int
    {
        return (int) ($this->provisioned_at?->diffInMinutes(now()) ?? $this->created_at?->diffInMinutes(now()) ?? 0);
    }

    /** A drain that's been running longer than the grace window — force-destroy. */
    public function isDrainOverdue(int $graceSeconds): bool
    {
        return $this->drain_started_at !== null
            && $this->drain_started_at->copy()->addSeconds($graceSeconds)->isPast();
    }
}
