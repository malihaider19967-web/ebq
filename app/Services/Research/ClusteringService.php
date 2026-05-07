<?php

namespace App\Services\Research;

use App\Models\Research\Keyword;
use App\Models\Research\KeywordCluster;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Services\Research\Niche\EmbeddingCache;
use App\Services\Research\Niche\EmbeddingProvider;
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
        private readonly ?EmbeddingProvider $embedding = null,
        private readonly float $embeddingThreshold = 0.6,
    ) {}

    /**
     * @param  iterable<Keyword>  $keywords
     * @return Collection<int, KeywordCluster>
     */
    public function cluster(iterable $keywords): Collection
    {
        $useEmbeddings = $this->embedding !== null && $this->embedding->isAvailable();

        $signatures = [];   // keyword_id => domain-set OR embedding vector
        $keywordById = [];  // keyword_id => Keyword
        $cache = $useEmbeddings ? new EmbeddingCache($this->embedding) : null;

        foreach ($keywords as $keyword) {
            $keywordById[$keyword->id] = $keyword;
            if ($useEmbeddings) {
                $vec = $cache?->forKeyword($keyword);
                if ($vec !== null) {
                    $signatures[$keyword->id] = $vec;
                }
            } else {
                $signatures[$keyword->id] = $this->topDomainsFor($keyword);
            }
        }

        if ($signatures === []) {
            return collect();
        }

        $parent = [];
        foreach (array_keys($signatures) as $id) {
            $parent[$id] = $id;
        }

        $find = function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }

            return $x;
        };

        $ids = array_keys($signatures);
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $ids[$i];
                $b = $ids[$j];

                if ($useEmbeddings) {
                    $sim = EmbeddingCache::cosine($signatures[$a], $signatures[$b]);
                    $hit = $sim >= $this->embeddingThreshold;
                } else {
                    $sim = $this->jaccard($signatures[$a], $signatures[$b]);
                    $hit = $sim >= $this->similarityThreshold;
                }

                if ($hit) {
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
                    'signal' => $useEmbeddings ? 'embedding' : 'serp_overlap',
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
