<?php

namespace App\Services\Research;

use App\Models\Research\Keyword;
use App\Models\Research\SerpFeature;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\Website;
use App\Services\Research\Quota\ResearchCostLogger;
use App\Services\Research\Quota\ResearchQuotaService;
use App\Services\SerperSearchClient;
use Illuminate\Support\Carbon;

/**
 * Pipeline 2 — wraps Serper organic search and persists the response into
 * serp_snapshots / serp_results / serp_features. Idempotent on
 * (keyword_id, device, country, location, fetched_on) by virtue of the
 * unique index — repeat calls within the same day are no-ops at the DB
 * level.
 */
class SerpIngestionService
{
    public function __construct(
        private readonly SerperSearchClient $serper,
        private readonly ResearchQuotaService $quota,
        private readonly ResearchCostLogger $cost,
    ) {}

    /**
     * Fetch + persist. Returns the snapshot or null when the API call
     * failed or quota was exhausted.
     */
    public function ingest(
        Keyword $keyword,
        string $device = 'desktop',
        ?string $location = null,
        ?Website $website = null,
        int $resultsCount = 10,
    ): ?SerpSnapshot {
        $this->quota->assertCanSpend($website, 'serp_fetch', 1);

        $existing = SerpSnapshot::query()
            ->where('keyword_id', $keyword->id)
            ->where('device', $device)
            ->where('country', $keyword->country)
            ->where('location', $location)
            ->whereDate('fetched_on', Carbon::today()->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $payload = $this->serper->query([
            'q' => $keyword->query,
            'num' => $resultsCount,
            'gl' => $keyword->country !== 'global' ? $keyword->country : null,
            'hl' => $keyword->language,
            'device' => $device,
            'location' => $location,
            'type' => 'organic',
            '__website_id' => $website?->id,
            '__source' => 'research',
        ]);

        if (! is_array($payload)) {
            return null;
        }

        $this->cost->log(
            resource: 'serp_fetch',
            websiteId: $website?->id,
            provider: 'serper',
            meta: ['keyword_id' => $keyword->id, 'device' => $device],
            units: 1,
        );

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'device' => $device,
            'country' => $keyword->country,
            'location' => $location,
            'provider' => 'serper',
            'fetched_at' => Carbon::now(),
            'fetched_on' => Carbon::today(),
            'raw_payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''),
        ]);

        $this->writeOrganic($snapshot, $payload['organic'] ?? []);
        $this->writeFeatureList($snapshot, $payload['peopleAlsoAsk'] ?? [], 'paa');
        $this->writeFeatureList($snapshot, $payload['relatedSearches'] ?? [], 'related');

        if (isset($payload['knowledgeGraph']) && is_array($payload['knowledgeGraph'])) {
            SerpFeature::create([
                'snapshot_id' => $snapshot->id,
                'feature_type' => 'knowledge_graph',
                'payload' => $payload['knowledgeGraph'],
            ]);
        }

        if (isset($payload['answerBox']) && is_array($payload['answerBox'])) {
            SerpFeature::create([
                'snapshot_id' => $snapshot->id,
                'feature_type' => 'answer_box',
                'payload' => $payload['answerBox'],
            ]);
        }

        return $snapshot;
    }

    /** @param list<mixed> $organic */
    private function writeOrganic(SerpSnapshot $snapshot, array $organic): void
    {
        $rank = 0;
        foreach ($organic as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rank++;
            $url = (string) ($row['link'] ?? $row['url'] ?? '');
            if ($url === '') {
                continue;
            }

            SerpResult::create([
                'snapshot_id' => $snapshot->id,
                'rank' => (int) ($row['position'] ?? $rank),
                'url' => $url,
                'domain' => $this->domainOf($url),
                'title' => isset($row['title']) ? mb_substr((string) $row['title'], 0, 512) : null,
                'snippet' => isset($row['snippet']) ? (string) $row['snippet'] : null,
                'result_type' => 'organic',
                'is_low_quality' => false,
            ]);
        }
    }

    /** @param list<mixed> $items */
    private function writeFeatureList(SerpSnapshot $snapshot, array $items, string $type): void
    {
        if ($items === []) {
            return;
        }
        SerpFeature::create([
            'snapshot_id' => $snapshot->id,
            'feature_type' => $type,
            'payload' => array_values(array_filter($items, 'is_array')),
        ]);
    }

    private function domainOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return mb_substr($url, 0, 255);
        }

        return mb_substr(preg_replace('/^www\./i', '', $host) ?? $host, 0, 255);
    }
}
