<?php

namespace App\Services;

use App\Jobs\FetchKeywordMetricsJob;
use App\Models\KeywordMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sole entrypoint for every Keywords Everywhere read in the app. All callers
 * go through this — never hit KeywordsEverywhereClient directly from a view or
 * component. This enforces the "DB-first, fetch last, never re-bill on fresh
 * data" contract.
 *
 * Shape overview:
 *   metricsFor/Many   — pure DB reads. Caller decides whether to act on
 *                        null or stale rows.
 *   metricsOrQueue    — DB read with a background refresh for missing/stale
 *                        keys. What the UI normally wants.
 *   refresh           — synchronous API call + upsert. The job + on-demand
 *                        button both come through here.
 */
class KeywordMetricsService
{
    public function __construct(private KeywordsEverywhereClient $client)
    {
    }

    public function metricsFor(string $keyword, string $country = 'global'): ?KeywordMetric
    {
        return KeywordMetric::query()
            ->where('keyword_hash', KeywordMetric::hashKeyword($keyword))
            ->where('country', $this->normalizeCountry($country))
            ->first();
    }

    /**
     * Bulk DB lookup. Returns array keyed by keyword_hash.
     *
     * @param  list<string>  $keywords
     * @return array<string, KeywordMetric>
     */
    public function metricsForMany(array $keywords, string $country = 'global'): array
    {
        $hashes = [];
        foreach ($keywords as $k) {
            $s = is_string($k) ? trim($k) : '';
            if ($s !== '') {
                $hashes[] = KeywordMetric::hashKeyword($s);
            }
        }
        if ($hashes === []) {
            return [];
        }

        return KeywordMetric::query()
            ->whereIn('keyword_hash', array_values(array_unique($hashes)))
            ->where('country', $this->normalizeCountry($country))
            ->get()
            ->keyBy('keyword_hash')
            ->all();
    }

    /**
     * Return what we have on file right now and dispatch a background refresh
     * for any keyword that's missing or stale. The UI gets fresh data on the
     * next Livewire tick once the job settles.
     *
     * @param  list<string>  $keywords
     * @return array<string, KeywordMetric>
     */
    public function metricsOrQueue(array $keywords, string $country = 'global', ?int $websiteId = null, ?int $ownerUserId = null): array
    {
        $country = $this->normalizeCountry($country);
        $cleaned = $this->uniqueCleaned($keywords);

        if ($cleaned === []) {
            return [];
        }

        $have = $this->metricsForMany($cleaned, $country);

        $toFetch = [];
        foreach ($cleaned as $kw) {
            $hash = KeywordMetric::hashKeyword($kw);
            $row = $have[$hash] ?? null;
            if ($row === null || ! $row->isFresh()) {
                $toFetch[] = $kw;
            }
        }

        if ($toFetch !== []) {
            FetchKeywordMetricsJob::dispatch($toFetch, $country, $websiteId, $ownerUserId);
        }

        return $have;
    }

    /**
     * Synchronous fetch + upsert. Returns the number of rows written.
     *
     * @param  list<string>  $keywords
     */
    public function refresh(array $keywords, string $country = 'global', ?int $websiteId = null, ?int $ownerUserId = null, ?string $source = null): int
    {
        $country = $this->normalizeCountry($country);
        $cleaned = $this->uniqueCleaned($keywords);

        if ($cleaned === []) {
            return 0;
        }

        Log::info('KeywordMetricsService.refresh: starting', [
            'count' => count($cleaned),
            'country' => $country,
            'website_id' => $websiteId,
            'sample' => array_slice($cleaned, 0, 3),
        ]);

        $response = $this->client->getKeywordData(
            $cleaned,
            $country,
            websiteId: $websiteId,
            ownerUserId: $ownerUserId,
            source: $source,
        );
        if ($response === null) {
            Log::warning('KeywordMetricsService.refresh: client returned null (check API key + log lines above)');

            return 0;
        }
        if (empty($response['data'])) {
            Log::warning('KeywordMetricsService.refresh: response had empty data', [
                'credits' => $response['credits'] ?? null,
            ]);

            return 0;
        }

        $freshDays = max(1, (int) config('services.keywords_everywhere.fresh_days', 30));
        $fetchedAt = Carbon::now();
        $expiresAt = $fetchedAt->copy()->addDays($freshDays);

        // Index the API response by the lowercase-trimmed keyword so we can
        // pair each input against whatever KE returned — even when the API
        // normalizes or drops a keyword. Critical: we always write under the
        // hash of OUR input keyword (not KE's returned string), otherwise
        // skip-fresh logic in the command/observer never matches on re-run.
        $responseIndex = [];
        foreach ($response['data'] as $row) {
            if (! is_array($row) || empty($row['keyword']) || ! is_string($row['keyword'])) {
                continue;
            }
            $responseIndex[mb_strtolower(trim($row['keyword']))] = $row;
        }

        $written = 0;
        foreach ($cleaned as $inputKeyword) {
            $key = mb_strtolower($inputKeyword);
            $row = $responseIndex[$key] ?? null;

            $vol = null;
            $cpcValue = null;
            $currency = null;
            $competition = null;
            $trend = null;

            if ($row !== null) {
                if (isset($row['vol']) && is_numeric($row['vol'])) {
                    $vol = (int) $row['vol'];
                }
                if (isset($row['cpc']) && is_array($row['cpc'])) {
                    if (isset($row['cpc']['value']) && is_numeric($row['cpc']['value'])) {
                        $cpcValue = (float) $row['cpc']['value'];
                    }
                    if (isset($row['cpc']['currency']) && is_string($row['cpc']['currency'])) {
                        $currency = strtoupper(trim($row['cpc']['currency'])) ?: null;
                    }
                }
                if (isset($row['competition']) && is_numeric($row['competition'])) {
                    $competition = (float) $row['competition'];
                }
                if (isset($row['trend']) && is_array($row['trend'])) {
                    $trend = array_values(array_filter($row['trend'], 'is_array'));
                }
            }

            // Write a row even when KE didn't return data for this input —
            // otherwise long-tail keywords KE doesn't know about would burn
            // credits on every scan. An empty row with fresh `expires_at` is
            // a "we asked, KE has nothing" cache entry.
            KeywordMetric::updateOrCreate(
                [
                    'keyword_hash' => KeywordMetric::hashKeyword($inputKeyword),
                    'country' => $country,
                    'data_source' => 'gkp',
                ],
                [
                    'keyword' => $inputKeyword,
                    'search_volume' => $vol,
                    'cpc' => $cpcValue,
                    'currency' => $currency,
                    'competition' => $competition,
                    'trend_12m' => $trend,
                    'fetched_at' => $fetchedAt,
                    'expires_at' => $expiresAt,
                ]
            );
            $written++;
        }

        Log::info('KeywordMetricsService.refresh: done', [
            'requested' => count($cleaned),
            'written' => $written,
            'matched_in_response' => count(array_intersect_key(
                array_flip(array_map('mb_strtolower', $cleaned)),
                $responseIndex
            )),
            'credits_remaining' => $response['credits'] ?? null,
        ]);

        return $written;
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function uniqueCleaned(array $keywords): array
    {
        $seen = [];
        $out = [];
        foreach ($keywords as $k) {
            $s = is_string($k) ? trim($k) : '';
            if ($s === '') {
                continue;
            }
            $key = mb_strtolower($s);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $s;
        }

        return $out;
    }

    private function normalizeCountry(string $country): string
    {
        $c = strtolower(trim($country));

        return $c === '' ? 'global' : $c;
    }
}
