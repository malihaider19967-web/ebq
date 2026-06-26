<?php

namespace App\Services\KeywordFinder;

use Illuminate\Support\Facades\Cache;

/**
 * Shared, calendar-month cache for keyword DISCOVERY results (the "ideas"
 * list — seed expansion or website-mode), keyed across every user so the
 * first person to look up a given seed set/URL warms it for everyone else,
 * not just themselves. Deliberately month-based (the cache key embeds
 * `Y-m`), not a rolling N-day TTL — "ignore the cache once the calendar
 * month turns over" was an explicit product decision, not a freshness
 * tuning knob. Separate from {@see \App\Services\KeywordMetricsService}'s
 * per-keyword volume cache (rolling 30 days, different data shape).
 */
class KeywordIdeasMonthlyCache
{
    private const PREFIX = 'kw-ideas-month:';

    /**
     * @param  array<string, mixed>  $payload  the normalized dispatchIdeas() payload
     *                                          (seeds+location+language, or url+scope+location+language)
     */
    public static function key(string $mode, array $payload): string
    {
        if (in_array($mode, ['website', 'page'], true)) {
            $signature = 'url:'.strtolower(trim((string) ($payload['url'] ?? ''))).':'.($payload['scope'] ?? 'site');
        } else {
            $seeds = array_map(
                static fn ($s): string => strtolower(trim((string) $s)),
                is_array($payload['seeds'] ?? null) ? $payload['seeds'] : [],
            );
            sort($seeds);
            $signature = 'seeds:'.implode('|', $seeds);
        }

        $location = strtolower(trim((string) ($payload['location'] ?? '')));
        $language = strtolower(trim((string) ($payload['language'] ?? '')));

        // Y-m in the key itself means a new month is automatically a cache
        // miss even if something bypassed the TTL — belt and suspenders.
        return self::PREFIX.now()->format('Y-m').':'.md5($signature.':'.$location.':'.$language);
    }

    /** @return list<array<string,mixed>>|null */
    public static function get(string $key): ?array
    {
        $value = Cache::get($key);

        return is_array($value) ? $value : null;
    }

    /** @param  list<array<string,mixed>>  $results */
    public static function put(string $key, array $results): void
    {
        // Expire exactly at month end rather than a fixed TTL, so it always
        // lines up with the "current month" framing regardless of when in
        // the month it was first written.
        Cache::put($key, $results, now()->endOfMonth());
    }
}
