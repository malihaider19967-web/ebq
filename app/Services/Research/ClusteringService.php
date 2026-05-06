<?php

namespace App\Services\Research;

use App\Models\Research\Keyword;
use App\Models\Research\KeywordCluster;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pipeline 5 — group keywords into clusters by SERP overlap (Jaccard on
 * the top-10 organic domains of their latest snapshots). Token / TF-IDF
 * fallback is left for Phase-2.5; embeddings is the Phase-4 swap behind
 * RESEARCH_EMBEDDINGS_ENABLED.
 *
 *  - Threshold default 0.4 — empirical sweet-spot for English SERPs.
 *  - Single-pass union-find; one pass per (country) shard.
 *  - Idempotent: re-running on the same input attaches existing keywords
 *    to existing clusters by centroid.
 */
class ClusteringService
{
    public function __construct(
        private readonly float $similarityThreshold = 0.4,
    ) {}

    /**
     * @param  iterable<Keyword>  $keywords
     * @return Collection<int, KeywordCluster>
     */
    public function cluster(iterable $keywords): Collection
    {
        $domainSets = [];   // keyword_id => set<domain>
        $keywordById = [];  // keyword_id => Keyword

        foreach ($keywords as $keyword) {
            $keywordById[$keyword->id] = $keyword;
            $domainSets[$keyword->id] = $this->topDomainsFor($keyword);
        }

        if ($domainSets === []) {
            return collect();
        }

        $parent = [];
        foreach (array_keys($domainSets) as $id) {
            $parent[$id] = $id;
        }

        $find = function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }

            return $x;
        };

        $ids = array_keys($domainSets);
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                if ($this->jaccard($domainSets[$a], $domainSets[$b]) >= $this->similarityThreshold) {
                    $ra = $find($a);
                    $rb = $find($b);
                    if ($ra !== $rb) {
                        $parent[$rb] = $ra;
                    }
                }
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $id) {
            $groups[$find((int) $id)][] = (int) $id;
        }

        $clusters = collect();
        foreach ($groups as $rootId => $memberIds) {
            $centroid = $keywordById[$rootId];
            $cluster = KeywordCluster::updateOrCreate(
                ['centroid_keyword_id' => $centroid->id],
                [
                    'cluster_name' => $centroid->normalized_query,
                    'signal' => 'serp_overlap',
                    'last_recomputed_at' => Carbon::now(),
                ]
            );
            $cluster->keywords()->syncWithoutDetaching(
                array_fill_keys($memberIds, ['confidence' => 1.0])
            );
            $clusters->push($cluster);
        }

        return $clusters;
    }

    /** @return array<string, true> */
    private function topDomainsFor(Keyword $keyword): array
    {
        $snapshot = SerpSnapshot::query()
            ->where('keyword_id', $keyword->id)
            ->orderByDesc('fetched_at')
            ->first();

        if ($snapshot === null) {
            return [];
        }

        return SerpResult::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('result_type', 'organic')
            ->orderBy('rank')
            ->limit(10)
            ->pluck('domain')
            ->mapWithKeys(fn ($d) => [(string) $d => true])
            ->all();
    }

    /**
     * @param  array<string, true>  $a
     * @param  array<string, true>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $intersection = count(array_intersect_key($a, $b));
        $union = count($a + $b);

        return $union === 0 ? 0.0 : $intersection / $union;
    }
}
