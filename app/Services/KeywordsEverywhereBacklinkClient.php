<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
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
 * KE charges per keyword (effectively per result row) on this endpoint, so
 * we trust the `credits_consumed` field the API itself returns and log that
 * as `units_consumed` on the activity row. Falls back to result-count if
 * the field is absent in some response shape.
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
    public function backlinksForDomain(
        string $domain,
        int $limit = 50,
        ?int $websiteId = null,
        ?int $ownerUserId = null,
    ): ?array {
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
                $items = [];
            }
            $items = array_values(array_filter($items, 'is_array'));

            // Trust KE's own credits_consumed field — it accounts for the
            // per-keyword billing model that this endpoint uses (one credit
            // per result row). Falls back to result count if missing, then
            // to 1 (every successful call costs at least one credit).
            $unitsConsumed = null;
            if (isset($json['credits_consumed']) && is_numeric($json['credits_consumed'])) {
                $unitsConsumed = max(0, (int) $json['credits_consumed']);
            } elseif ($items !== []) {
                $unitsConsumed = count($items);
            } else {
                $unitsConsumed = 1;
            }

            $creditsRemaining = null;
            if (isset($json['credits']) && is_numeric($json['credits'])) {
                $creditsRemaining = (int) $json['credits'];
            }

            app(ClientActivityLogger::class)->log(
                'api_usage.keywords_everywhere',
                userId: $ownerUserId ?? Auth::id(),
                websiteId: $websiteId,
                provider: 'keywords_everywhere',
                meta: [
                    'operation' => 'backlinks_for_domain',
                    'domain' => $domain,
                    'limit' => $limit,
                    'returned' => count($items),
                    'credits_consumed' => $unitsConsumed,
                    'credits_remaining' => $creditsRemaining,
                    'country' => $country,
                    'data_source' => $dataSource,
                ],
                unitsConsumed: $unitsConsumed,
            );

            return $items;
        } catch (\Throwable $e) {
            Log::warning('KeywordsEverywhere backlinks request threw', [
                'domain' => $domain,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
