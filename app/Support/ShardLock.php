<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Per-tenant / per-crawl-site "migrating" lock. Set by {@see \App\Services\Sharding\ShardMover}
 * for the duration of a move; checked by tenant/crawl write jobs which re-queue
 * themselves (`$this->release()`) while it's held, so no write lands on the
 * source node between the copy and the source purge (the move's only data-loss
 * window). Cleared when the move completes.
 *
 * Backed by the shared Redis cache (visible to the web box + every worker box).
 * A safety TTL guarantees a crashed move can't lock a tenant out forever.
 */
class ShardLock
{
    /** Safety expiry — a move is seconds; this is the crash backstop. */
    public const TTL = 900;

    public static function lockWebsite(string $websiteId): void
    {
        Cache::put(self::websiteKey($websiteId), now()->timestamp, self::TTL);
    }

    public static function unlockWebsite(string $websiteId): void
    {
        Cache::forget(self::websiteKey($websiteId));
    }

    public static function websiteLocked(?string $websiteId): bool
    {
        return $websiteId !== null && $websiteId !== '' && Cache::has(self::websiteKey($websiteId));
    }

    public static function lockCrawlSite(string $crawlSiteId): void
    {
        Cache::put(self::crawlKey($crawlSiteId), now()->timestamp, self::TTL);
    }

    public static function unlockCrawlSite(string $crawlSiteId): void
    {
        Cache::forget(self::crawlKey($crawlSiteId));
    }

    public static function crawlSiteLocked(?string $crawlSiteId): bool
    {
        return $crawlSiteId !== null && $crawlSiteId !== '' && Cache::has(self::crawlKey($crawlSiteId));
    }

    private static function websiteKey(string $id): string
    {
        return 'shard:migrating:w:'.$id;
    }

    private static function crawlKey(string $id): string
    {
        return 'shard:migrating:cs:'.$id;
    }
}
