<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backlinks-by-domain wrapper over the Keywords Everywhere API. Reuses the
 * same Bearer-auth credential as KeywordsEverywhereClient.
 *
 * Default endpoint: POST /v1/get_domain_backlinks
 * Request body (form-encoded):
 *   domain      — competitor domain (bare, no protocol)
 *   num         — how many results to return (capped by our config)
 *   country     — 2-letter ISO (default 'us')
 *   currency    — 'USD'
 *   dataSource  — 'g'
 *
 * Response shape:
 *   { "data": [{ anchor_text, domain_source, domain_target,
 *                url_source, url_target }, ...],
 *     "credits_consumed": 1, "time_taken": 4.9 }
 *
 * Endpoint + body knobs are env-overridable so we can adapt without a code
 * push. Returns null on any failure — callers log and skip rather than crash.
 */
class KeywordsEverywhereBacklinkClient
{
    private const DEFAULT_ENDPOINT = '/v1/get_domain_backlinks';

    /**
     * @return list<array<string, mixed>>|null
     */
    public function backlinksForDomain(string $domain, int $limit = 50): ?array
    {
        $key = config('services.keywords_everywhere.key');
        if (! is_string($key) || trim($key) === '') {
            Log::warning('KeywordsEverywhereBacklinkClient: missing API key');

            return null;
        }

        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.keywords_everywhere.base_url', 'https://api.keywordseverywhere.com'), '/');
        $endpoint = (string) config('services.keywords_everywhere.backlinks_endpoint', self::DEFAULT_ENDPOINT);
        $url = $baseUrl.(str_starts_with($endpoint, '/') ? $endpoint : '/'.$endpoint);

        $limit = max(1, min(1000, $limit));
        $country = strtolower((string) config('services.keywords_everywhere.backlinks_country', 'us'));
        $currency = strtoupper((string) config('services.keywords_everywhere.backlinks_currency', 'USD'));
        $dataSource = (string) config('services.keywords_everywhere.backlinks_data_source', 'g');

        try {
            $response = Http::timeout(60)
                ->connectTimeout(8)
                ->withHeaders([
                    'Authorization' => 'Bearer '.trim($key),
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post($url, [
                    'domain' => $domain,
                    'num' => $limit,
                    'country' => $country,
                    'currency' => $currency,
                    'dataSource' => $dataSource,
                ]);

            if ($response->failed()) {
                Log::warning('KeywordsEverywhere backlinks HTTP failure', [
                    'status' => $response->status(),
                    'domain' => $domain,
                    'body_snippet' => substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                Log::warning('KeywordsEverywhere backlinks malformed response', [
                    'domain' => $domain,
                    'body_snippet' => substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            // Accept a handful of wrapper shapes: data[], backlinks[], results[], or a bare array.
            $items = $json['data'] ?? $json['backlinks'] ?? $json['results'] ?? null;
            if ($items === null && array_is_list($json)) {
                $items = $json;
            }
            if (! is_array($items)) {
                return [];
            }

            return array_values(array_filter($items, 'is_array'));
        } catch (\Throwable $e) {
            Log::warning('KeywordsEverywhere backlinks request threw', [
                'domain' => $domain,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
