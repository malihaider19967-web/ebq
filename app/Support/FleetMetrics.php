<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;

/**
 * Per-physical-box queue counters for the crawl-worker fleet. The crawl queue is
 * ONE shared Redis queue, so Horizon's metrics are per-queue, not per-box — these
 * counters fill that gap: each worker box stamps its own in-flight / finished /
 * failed counts (keyed by its FLEET_NODE_ID) into the shared Redis, and the
 * crawler fleet page on the web box reads them back per node. Drives the
 * "is this box safe to drain?" view.
 *
 * Written by queue events on the worker boxes ({@see App\Providers\AppServiceProvider}),
 * reset/cleaned by the fleet lifecycle ({@see App\Services\Fleet\WorkerFleetService}).
 */
class FleetMetrics
{
    private static function key(string $nodeId, string $metric): string
    {
        return "fleet:node:{$nodeId}:{$metric}";
    }

    /** Same Redis the queues live on, so worker writes + web reads share keys. */
    private static function redis()
    {
        return Redis::connection();
    }

    // The in-flight gauge tracks ATTEMPTS, so it must be decremented for every way an
    // attempt can end — success (JobProcessed), thrown (JobExceptionOccurred, which
    // precedes both a retry-release AND a permanent JobFailed), so JobFailed must NOT
    // also decrement or a failed job over-decrements. Otherwise a retried/failed job
    // leaks the gauge (incr per attempt, decr once) — seen as a stuck-high "in-flight".
    public static function onProcessing(string $nodeId): void
    {
        self::redis()->incr(self::key($nodeId, 'running'));
    }

    /** Attempt succeeded → leave the gauge, bump cumulative done. */
    public static function onProcessed(string $nodeId): void
    {
        self::decrRunning($nodeId);
        self::redis()->incr(self::key($nodeId, 'done'));
    }

    /** Attempt threw (fires before a retry-release OR a permanent fail) → leave the gauge. */
    public static function onException(string $nodeId): void
    {
        self::decrRunning($nodeId);
    }

    /** Job exhausted its retries → cumulative failed only (gauge already left by onException). */
    public static function onFailed(string $nodeId): void
    {
        self::redis()->incr(self::key($nodeId, 'failed'));
    }

    private static function decrRunning(string $nodeId): void
    {
        $r = self::redis();
        if ((int) $r->decr(self::key($nodeId, 'running')) < 0) {
            $r->set(self::key($nodeId, 'running'), 0);
        }
    }

    /**
     * @return array{running:int,done:int,failed:int}
     */
    public static function for(string $nodeId): array
    {
        $r = self::redis();

        return [
            'running' => max(0, (int) $r->get(self::key($nodeId, 'running'))),
            'done' => (int) $r->get(self::key($nodeId, 'done')),
            'failed' => (int) $r->get(self::key($nodeId, 'failed')),
        ];
    }

    /** Counts for many nodes at once. @return array<string,array{running:int,done:int,failed:int}> */
    public static function forMany(iterable $nodeIds): array
    {
        $out = [];
        foreach ($nodeIds as $id) {
            $out[$id] = self::for((string) $id);
        }

        return $out;
    }

    /** Zero the in-flight gauge — call when a box's workers (re)start, to clear crash drift. */
    public static function resetRunning(string $nodeId): void
    {
        self::redis()->set(self::key($nodeId, 'running'), 0);
    }

    /** Remove all counters for a destroyed node. */
    public static function clear(string $nodeId): void
    {
        self::redis()->del(
            self::key($nodeId, 'running'),
            self::key($nodeId, 'done'),
            self::key($nodeId, 'failed'),
        );
    }
}
