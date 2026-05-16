<?php

namespace App\Services;

use App\Models\User;
use App\Services\Usage\UsageMeter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Thin wrapper around the Keywords Everywhere bulk-metrics endpoint.
 * Shape matches SerperSearchClient: reads config, returns null on missing
 * key or HTTP failure, logs a warning, never throws. Retry/backoff is the
 * caller's job (done at the queue-job level).
 *
 * Reference: POST https://api.keywordseverywhere.com/v1/get_keyword_data
 * Request (form-encoded or JSON):
 *   - kw[]       repeated keyword strings (max 100 per call)
 *   - country    global | us | uk | ca | au | in | nz | za (case-insensitive)
 *   - currency   usd | gbp | cad | aud | inr | nzd | zar | eur (etc.)
 *   - dataSource gkp (Google Keyword Planner) | cli (Clickstream)
 * Response:
 *   { data: [{ keyword, vol, cpc: { currency, value }, competition,
 *              trend: [{ month, year, value }, ...] }, ...],
 *     credits: <remaining-int> }
 */
class KeywordsEverywhereClient
{
    private const ENDPOINT = '/v1/get_keyword_data';
    private const MAX_KEYWORDS_PER_CALL = 100;

    /**
     * Fetch metrics for a batch of keywords. Returns null on any failure
     * (missing key, HTTP error, malformed body). Multiple API calls are
     * chunked transparently — return shape aggregates all data rows.
     *
     * Pass `$websiteId` and `$ownerUserId` so the per-call activity row in
     * `client_activities` carries the right billing scope. The admin usage
     * page sums `units_consumed` keyed off these.
     *
     * @param  list<string>  $keywords
     * @return array{data: list<array<string, mixed>>, credits: ?int}|null
     */
    public function getKeywordData(
        array $keywords,
        string $country = 'global',
        string $currency = 'usd',
        string $dataSource = 'gkp',
        ?int $websiteId = null,
        ?int $ownerUserId = null,
        ?string $source = null,
    ): ?array {
        $key = config('services.keywords_everywhere.key');
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $clean = [];
        foreach ($keywords as $k) {
            $s = is_string($k) ? trim($k) : '';
            if ($s !== '') {
                $clean[] = $s;
            }
        }
        $clean = array_values(array_unique($clean));
        if ($clean === []) {
            return ['data' => [], 'credits' => null];
        }

        $baseUrl = rtrim((string) config('services.keywords_everywhere.base_url', 'https://api.keywordseverywhere.com'), '/');
        $url = $baseUrl.self::ENDPOINT;

        $country = strtolower(trim($country)) ?: 'global';
        $currency = strtolower(trim($currency)) ?: 'usd';
        $dataSource = in_array(strtolower(trim($dataSource)), ['gkp', 'cli'], true) ? strtolower(trim($dataSource)) : 'gkp';

        $allData = [];
        $credits = null;

        $billedUser = $this->resolveBilledUser($websiteId, $ownerUserId);
        if ($billedUser !== null) {
            // Pre-flight: throw 402 before we burn KE credits if the user's
            // monthly cap can't cover this whole batch. Throws
            // QuotaExceededException which the global handler renders as
            // structured JSON for API/plugin callers.
            app(UsageMeter::class)->assertCanSpend($billedUser, 'keywords_everywhere', count($clean));
        }

        foreach (array_chunk($clean, self::MAX_KEYWORDS_PER_CALL) as $chunk) {
            try {
                $response = Http::timeout(30)
                    ->connectTimeout(5)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.trim($key),
                        'Accept' => 'application/json',
                    ])
                    ->asForm()
                    ->post($url, [
                        'kw' => $chunk,
                        'country' => $country,
                        'currency' => $currency,
                        'dataSource' => $dataSource,
                    ]);

                if ($response->failed()) {
                    Log::warning('KeywordsEverywhere HTTP failure', [
                        'status' => $response->status(),
                        'body_snippet' => substr((string) $response->body(), 0, 500),
                        'count' => count($chunk),
                    ]);

                    return null;
                }

                $json = $response->json();
                if (! is_array($json) || ! isset($json['data']) || ! is_array($json['data'])) {
                    Log::warning('KeywordsEverywhere malformed response', [
                        'body_snippet' => substr((string) $response->body(), 0, 500),
                    ]);

                    return null;
                }

                foreach ($json['data'] as $row) {
                    if (is_array($row)) {
                        $allData[] = $row;
                    }
                }
                if (isset($json['credits']) && is_numeric($json['credits'])) {
                    $credits = (int) $json['credits'];
                }

                // KE bills 1 credit per keyword in the request — even keywords
                // they have no data for. So `count($chunk)` is the correct
                // unit count regardless of how many rows came back in `data`.
                $meta = [
                    'operation' => 'search_volume_lookup',
                    'keyword_count' => count($chunk),
                    'credits_remaining' => $credits,
                    'country' => $country,
                    'data_source' => $dataSource,
                ];
                if ($source !== null && $source !== '') {
                    $meta['source'] = $source;
                }
                app(ClientActivityLogger::class)->log(
                    'api_usage.keywords_everywhere',
                    userId: $ownerUserId ?? Auth::id(),
                    websiteId: $websiteId,
                    provider: 'keywords_everywhere',
                    meta: $meta,
                    unitsConsumed: count($chunk),
                );
            } catch (\Throwable $e) {
                Log::warning('KeywordsEverywhere request threw', [
                    'message' => $e->getMessage(),
                    'count' => count($chunk),
                ]);

                return null;
            }
        }

        return ['data' => $allData, 'credits' => $credits];
    }

    /**
     * Resolve the billed user for cap enforcement. Mirrors
     * ClientActivityLogger's attribution: prefer the website owner, fall
     * back to the explicit owner id, finally the authenticated user.
     */
    private function resolveBilledUser(?int $websiteId, ?int $ownerUserId): ?User
    {
        if ($websiteId !== null) {
            $ownerId = \Illuminate\Support\Facades\DB::table('website_user')
                ->where('website_id', $websiteId)
                ->where('role', \App\Support\TeamPermissions::ROLE_OWNER)
                ->value('user_id');
            if ($ownerId === null) {
                $ownerId = \Illuminate\Support\Facades\DB::table('websites')
                    ->where('id', $websiteId)
                    ->value('user_id');
            }
            if ($ownerId !== null) {
                return User::find((int) $ownerId);
            }
        }
        $id = $ownerUserId ?? Auth::id();
        return $id ? User::find($id) : null;
    }
}
