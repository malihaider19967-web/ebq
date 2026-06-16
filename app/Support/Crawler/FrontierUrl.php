<?php

namespace App\Support\Crawler;

/**
 * Canonicalizes a URL for the crawl frontier by stripping noisy query-string
 * parameters (keeping an allowlist such as pagination). This collapses variants
 * like /id?name=titan, /id?name=max, /id?utm_source=x into a single /id row,
 * preventing parameter-space explosion in the inventory + findings.
 *
 * Controlled by config('crawler.strip_query_params') + keep_query_params.
 */
class FrontierUrl
{
    public static function collapse(string $url): string
    {
        if (! config('crawler.strip_query_params', true)) {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['query']) || empty($parts['host'])) {
            return $url;
        }

        parse_str((string) $parts['query'], $params);
        $keep = array_map('strtolower', (array) config('crawler.keep_query_params', []));
        $filtered = array_filter(
            $params,
            static fn ($k): bool => in_array(strtolower((string) $k), $keep, true),
            ARRAY_FILTER_USE_KEY
        );

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = $filtered !== [] ? '?'.http_build_query($filtered) : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }
}
