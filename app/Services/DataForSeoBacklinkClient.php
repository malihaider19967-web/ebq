<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal DataForSEO wrapper for the Backlinks API. Returns null on any
 * failure — caller handles gracefully (usually by logging + skipping the
 * competitor for this run). Retries/backoff live at the job level.
 *
 * Endpoint used:
 *   POST https://api.dataforseo.com/v3/backlinks/backlinks/live
 *
 * Request body (JSON array of tasks):
 *   [{ "target": "example.com", "limit": 50, "mode": "one_per_domain",
 *      "filters": [["dofollow", "=", true]] }]
 *
 * Response:
 *   {
 *     "tasks": [{
 *       "result": [{
 *         "items": [{ "url_from": "...", "domain_from": "...",
 *                     "anchor": "...", "domain_from_rank": 42,
 *                     "dofollow": true, "first_seen": "2024-01-02" }, ...]
 *       }]
 *     }]
 *   }
 */
class DataForSeoBacklinkClient
{
    private const ENDPOINT = '/v3/backlinks/backlinks/live';

    /**
     * Fetch up to $limit backlinks for a single target domain.
     *
     * @return list<array<string, mixed>>|null
     */
    public function backlinksForDomain(string $domain, int $limit = 50): ?array
    {
        $login = config('services.dataforseo.login');
        $password = config('services.dataforseo.password');
        if (! is_string($login) || trim($login) === '' || ! is_string($password) || trim($password) === '') {
            return null;
        }

        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.dataforseo.base_url', 'https://api.dataforseo.com'), '/');
        $url = $baseUrl.self::ENDPOINT;
        $limit = max(1, min(1000, $limit));

        try {
            $response = Http::timeout(60)
                ->connectTimeout(8)
                ->withBasicAuth(trim($login), trim($password))
                ->acceptJson()
                ->post($url, [[
                    'target' => $domain,
                    'limit' => $limit,
                    'mode' => 'one_per_domain', // Diverse referring domains, not all backlinks from the same site.
                    'order_by' => ['domain_from_rank,desc'],
                ]]);

            if ($response->failed()) {
                Log::warning('DataForSEO backlinks HTTP failure', [
                    'status' => $response->status(),
                    'domain' => $domain,
                    'body_snippet' => substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json) || empty($json['tasks']) || ! is_array($json['tasks'])) {
                Log::warning('DataForSEO malformed response', [
                    'domain' => $domain,
                    'body_snippet' => substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            $task = $json['tasks'][0] ?? null;
            if (! is_array($task)) {
                return [];
            }

            // Task-level error (e.g., invalid target, rate-limit, credit-exhausted).
            if (isset($task['status_code']) && (int) $task['status_code'] >= 40000) {
                Log::warning('DataForSEO task error', [
                    'domain' => $domain,
                    'status_code' => $task['status_code'],
                    'status_message' => $task['status_message'] ?? null,
                ]);

                return null;
            }

            $items = $task['result'][0]['items'] ?? [];

            return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        } catch (\Throwable $e) {
            Log::warning('DataForSEO request threw', [
                'domain' => $domain,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
