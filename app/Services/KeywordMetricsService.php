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
    public function metricsOrQueue(array $keywords, string $country = 'global'): array
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
            FetchKeywordMetricsJob::dispatch($toFetch, $country);
        }

        return $have;
    }

    /**
     * Synchronous fetch + upsert. Returns the number of rows written.
     *
     * @param  list<string>  $keywords
     */
    public function refresh(array $keywords, string $country = 'global'): int
    {
        $country = $this->normalizeCountry($country);
        $cleaned = $this->uniqueCleaned($keywords);

        if ($cleaned === []) {
            return 0;
        }

        Log::info('KeywordMetricsService.refresh: starting', [
            'count' => count($cleaned),
            'country' => $country,
            'sample' => array_slice($cleaned, 0, 3),
        ]);

        $response = $this->client->getKeywordData($cleaned, $country);
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

        $written = 0;
        foreach ($response['data'] as $row) {
            if (! is_array($row) || empty($row['keyword']) || ! is_string($row['keyword'])) {
                continue;
            }

            $keyword = trim($row['keyword']);
            if ($keyword === '') {
                continue;
            }

            $cpcValue = null;
            $currency = null;
            if (isset($row['cpc']) && is_array($row['cpc'])) {
                if (isset($row['cpc']['value']) && is_numeric($row['cpc']['value'])) {
                    $cpcValue = (float) $row['cpc']['value'];
                }
                if (isset($row['cpc']['currency']) && is_string($row['cpc']['currency'])) {
                    $currency = strtoupper(trim($row['cpc']['currency'])) ?: null;
                }
            }

            $trend = null;
            if (isset($row['trend']) && is_array($row['trend'])) {
                $trend = array_values(array_filter($row['trend'], 'is_array'));
            }

            KeywordMetric::updateOrCreate(
                [
                    'keyword_hash' => KeywordMetric::hashKeyword($keyword),
                    'country' => $country,
                    'data_source' => 'gkp',
                ],
                [
                    'keyword' => $keyword,
                    'search_volume' => isset($row['vol']) && is_numeric($row['vol']) ? (int) $row['vol'] : null,
                    'cpc' => $cpcValue,
                    'currency' => $currency,
                    'competition' => isset($row['competition']) && is_numeric($row['competition']) ? (float) $row['competition'] : null,
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
