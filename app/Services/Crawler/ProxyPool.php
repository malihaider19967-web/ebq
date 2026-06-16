<?php

namespace App\Services\Crawler;

use App\Models\Proxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The crawler's outbound proxy pool. Merges admin-managed proxies (the `proxies`
 * table) with proxies parsed from proxylist.txt in the project root, normalises
 * them to Guzzle proxy URLs, and hands out one at a time for anti-blocking
 * retries. DB-backed proxies get basic health tracking (auto-disabled after
 * repeated failures).
 */
class ProxyPool
{
    private const CACHE_KEY = 'crawler:proxypool:urls';
    private const CACHE_TTL = 60;

    public function enabled(): bool
    {
        return (bool) config('crawler.proxy.enabled', false)
            && config('crawler.proxy.mode', 'off') !== 'off';
    }

    public function available(): bool
    {
        return $this->enabled() && $this->urls() !== [];
    }

    /** Should this site's pages be proxied from the first attempt? */
    public function preemptiveFor(?string $crawlProtection): bool
    {
        if (! $this->available()) {
            return false;
        }
        $mode = config('crawler.proxy.mode', 'on_block');

        return $mode === 'always'
            || ($mode === 'on_block' && in_array($crawlProtection, ['cloudflare', 'blocked'], true));
    }

    /** Pick a proxy URL at random, or null if the pool is empty/disabled. */
    public function pick(): ?string
    {
        $urls = $this->urls();
        if ($urls === []) {
            return null;
        }

        return $urls[array_rand($urls)];
    }

    /** @return list<string> merged, normalised proxy URLs (cached briefly) */
    public function urls(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $urls = [];
            foreach (Proxy::query()->where('active', true)->pluck('url') as $u) {
                $n = $this->normalise((string) $u);
                if ($n !== null) {
                    $urls[$n] = true;
                }
            }

            return array_keys($urls);
        });
    }

    public function markSuccess(?string $url): void
    {
        if ($url === null) {
            return;
        }
        Proxy::where('url_hash', Proxy::hashUrl($url))->update([
            'fail_count' => 0,
            'success_count' => DB::raw('success_count + 1'),
            'last_used_at' => now(),
            'last_ok_at' => now(),
        ]);
    }

    public function markFailure(?string $url): void
    {
        if ($url === null) {
            return;
        }
        $max = (int) config('crawler.proxy.max_failures', 5);
        $proxy = Proxy::where('url_hash', Proxy::hashUrl($url))->first();
        if (! $proxy) {
            return; // file-sourced proxy — not health-tracked here
        }
        $proxy->fail_count++;
        $proxy->last_used_at = now();
        if ($max > 0 && $proxy->fail_count >= $max) {
            $proxy->active = false;
            Cache::forget(self::CACHE_KEY); // drop it from the live pool now
        }
        $proxy->save();
    }

    /**
     * Normalise a proxy entry to a Guzzle proxy URL: scheme://[user:pass@]host:port.
     * Accepts: host:port | host:port:user:pass | user:pass@host:port |
     * scheme://host:port | scheme://user:pass@host:port. Default scheme http.
     */
    public function normalise(string $entry): ?string
    {
        $entry = trim($entry);
        if ($entry === '') {
            return null;
        }

        $scheme = 'http';
        if (preg_match('#^(https?|socks5h?|socks4)://(.*)$#i', $entry, $m)) {
            $scheme = strtolower($m[1]);
            $entry = $m[2];
        }

        $user = $pass = null;
        if (str_contains($entry, '@')) {
            [$creds, $entry] = explode('@', $entry, 2);
            if (str_contains($creds, ':')) {
                [$user, $pass] = explode(':', $creds, 2);
            }
        }

        $parts = explode(':', $entry);
        // host:port:user:pass (common flat-file form)
        if ($user === null && count($parts) === 4) {
            [$host, $port, $user, $pass] = $parts;
        } elseif (count($parts) === 2) {
            [$host, $port] = $parts;
        } else {
            return null;
        }

        $host = trim($host);
        $port = (int) $port;
        if ($host === '' || $port < 1 || $port > 65535) {
            return null;
        }

        $auth = ($user !== null && $user !== '') ? rawurlencode($user).':'.rawurlencode((string) $pass).'@' : '';

        return "{$scheme}://{$auth}{$host}:{$port}";
    }
}
