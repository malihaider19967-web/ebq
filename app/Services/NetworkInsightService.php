<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use Illuminate\Support\Facades\Cache;

/**
 * Anonymized cross-network SERP insight aggregator.
 *
 * RankMath sees one site at a time. EBQ sees patterns across the
 * entire connected network — what schemas, word counts, link patterns,
 * and SERP features pages ranking #1–3 for a given keyword tend to
 * share. Tools inject these aggregates into prompts so the writer
 * can match the patterns that actually win.
 *
 * Privacy
 * ───────
 *   - Minimum cohort size 5 sites (otherwise return null with reason
 *     'insufficient_cohort'). Below that threshold, individual sites
 *     can be re-identified from the aggregate.
 *   - No domain names ever returned — only counts and percentages.
 *   - Cached 24h in the default cache to keep cost down (these
 *     aggregates change slowly).
 */
class NetworkInsightService
{
    private const CACHE_TTL_SECONDS = 86400;
    private const MIN_COHORT = 5;

    /**
     * Aggregate insight for a keyword across the network.
     *
     * @return array{
     *   keyword: string,
     *   country: string,
     *   cohort_size: int,
     *   reason?: string,
     *   word_count?: array{p25:int,p50:int,p75:int,p90:int},
     *   schema_types?: list<array{type:string,share_pct:int}>,
     *   serp_features?: list<array{feature:string,share_pct:int}>,
     *   typical_headings?: int,
     *   typical_internal_links?: int,
     *   typical_images?: int,
     *   common_entities?: list<string>
     * }|null
     */
    public function forKeyword(string $keyword, string $country = 'us'): ?array
    {
        $key = trim($keyword);
        if ($key === '') {
            return null;
        }
        $country = strtolower($country) ?: 'us';

        $cacheKey = sprintf('network_insight:v1:%s:%s', $country, hash('xxh3', mb_strtolower($key)));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->compute($key, $country);
        Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function compute(string $keyword, string $country): ?array
    {
        $kwHash = RankTrackingKeyword::hashKeyword($keyword);

        // Cohort = distinct websites tracking this kw (in this country).
        $kws = RankTrackingKeyword::query()
            ->where('keyword_hash', $kwHash)
            ->where('country', $country)
            ->where('is_active', true)
            ->get(['id', 'website_id']);

        $websiteIds = $kws->pluck('website_id')->unique()->values();
        if ($websiteIds->count() < self::MIN_COHORT) {
            return [
                'keyword' => $keyword,
                'country' => $country,
                'cohort_size' => $websiteIds->count(),
                'reason' => 'insufficient_cohort',
            ];
        }

        // Most recent snapshot per kw, keep only ones with top_results
        // populated (we rely on the captured top-3 to derive features).
        $snapshots = RankTrackingSnapshot::query()
            ->whereIn('rank_tracking_keyword_id', $kws->pluck('id'))
            ->orderByDesc('checked_at')
            ->get([
                'rank_tracking_keyword_id', 'serp_features', 'top_results', 'checked_at',
            ])
            ->groupBy('rank_tracking_keyword_id')
            ->map(fn ($group) => $group->first());

        $featureCounts = [];
        $totalSnapshots = 0;
        $topUrls = [];

        foreach ($snapshots as $snap) {
            if (! $snap) {
                continue;
            }
            $totalSnapshots++;

            $features = $this->jsonOrArray($snap->serp_features);
            foreach ($features as $f) {
                if (is_string($f) && $f !== '') {
                    $featureCounts[$f] = ($featureCounts[$f] ?? 0) + 1;
                }
            }

            $top = $this->jsonOrArray($snap->top_results);
            foreach (array_slice($top, 0, 3) as $row) {
                if (is_array($row) && is_string($row['url'] ?? null)) {
                    $topUrls[] = $row['url'];
                }
            }
        }

        $serpFeatures = [];
        foreach ($featureCounts as $name => $count) {
            $serpFeatures[] = [
                'feature' => $name,
                'share_pct' => (int) round(($count / max(1, $totalSnapshots)) * 100),
            ];
        }
        usort($serpFeatures, static fn ($a, $b) => $b['share_pct'] <=> $a['share_pct']);

        // Word count + schema types + entities derived from PageAuditReport
        // for any URL we've audited that matches a top-3 SERP URL above.
        // This is the strongest signal — pages we actually crawled +
        // analysed. Cohort still needs to be ≥5 distinct sites.
        $audits = PageAuditReport::query()
            ->whereIn('page_url', array_unique($topUrls))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['page_url', 'website_id', 'result']);

        $auditByUrl = [];
        foreach ($audits as $audit) {
            $url = (string) $audit->page_url;
            if (! isset($auditByUrl[$url])) {
                $auditByUrl[$url] = $audit;
            }
        }

        $wordCounts = [];
        $schemaCounts = [];
        $headingsList = [];
        $linksList = [];
        $imagesList = [];
        $entityCounts = [];

        foreach ($auditByUrl as $audit) {
            $r = is_array($audit->result) ? $audit->result : [];
            if (isset($r['word_count']) && is_numeric($r['word_count'])) {
                $wordCounts[] = (int) $r['word_count'];
            }
            foreach ((array) ($r['schema_types'] ?? []) as $st) {
                if (is_string($st) && $st !== '') {
                    $schemaCounts[$st] = ($schemaCounts[$st] ?? 0) + 1;
                }
            }
            if (isset($r['heading_count']) && is_numeric($r['heading_count'])) {
                $headingsList[] = (int) $r['heading_count'];
            } elseif (isset($r['h2']) && is_array($r['h2'])) {
                $headingsList[] = count($r['h2']);
            }
            if (isset($r['link_count']) && is_numeric($r['link_count'])) {
                $linksList[] = (int) $r['link_count'];
            }
            if (isset($r['image_count']) && is_numeric($r['image_count'])) {
                $imagesList[] = (int) $r['image_count'];
            }
            foreach ((array) ($r['entities'] ?? []) as $e) {
                $name = is_string($e) ? $e : (is_array($e) ? (string) ($e['name'] ?? '') : '');
                $name = trim($name);
                if ($name !== '') {
                    $entityCounts[$name] = ($entityCounts[$name] ?? 0) + 1;
                }
            }
        }

        $schemaTotal = max(1, count($auditByUrl));
        $schemaTypes = [];
        foreach ($schemaCounts as $type => $count) {
            $schemaTypes[] = ['type' => $type, 'share_pct' => (int) round(($count / $schemaTotal) * 100)];
        }
        usort($schemaTypes, static fn ($a, $b) => $b['share_pct'] <=> $a['share_pct']);

        arsort($entityCounts);
        $commonEntities = array_slice(array_keys($entityCounts), 0, 10);

        return [
            'keyword' => $keyword,
            'country' => $country,
            'cohort_size' => $websiteIds->count(),
            'word_count' => $this->percentiles($wordCounts),
            'schema_types' => array_slice($schemaTypes, 0, 8),
            'serp_features' => array_slice($serpFeatures, 0, 8),
            'typical_headings' => $this->medianInt($headingsList),
            'typical_internal_links' => $this->medianInt($linksList),
            'typical_images' => $this->medianInt($imagesList),
            'common_entities' => $commonEntities,
        ];
    }

    /**
     * @param  list<int>  $values
     * @return array{p25:int,p50:int,p75:int,p90:int}|null
     */
    private function percentiles(array $values): ?array
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $pick = fn (float $p): int => (int) $values[(int) min($n - 1, max(0, floor($p * ($n - 1))))];
        return [
            'p25' => $pick(0.25),
            'p50' => $pick(0.50),
            'p75' => $pick(0.75),
            'p90' => $pick(0.90),
        ];
    }

    /** @param list<int> $values */
    private function medianInt(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        return (int) $values[(int) floor((count($values) - 1) / 2)];
    }

    /** @return array<int|string, mixed> */
    private function jsonOrArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
