<?php

namespace App\Services\Research\Intelligence;

use App\Models\Research\KeywordCluster;
use App\Models\Research\KeywordIntelligence;
use App\Models\Research\Niche;
use App\Models\Research\NicheTopicCluster;
use Illuminate\Support\Collection;

/**
 * Wraps keyword_clusters + niche_topic_clusters: rolls up demand + avg
 * difficulty + priority per topic. Replaces the cooccurrence-only
 * behaviour of TopicalAuthorityService for sites with enough SERP data
 * to populate clusters via ClusteringService; the older service stays as
 * the cold-start fallback.
 */
class TopicEngine
{
    /**
     * Recompute the niche_topic_clusters cache for one niche from the
     * underlying keyword_clusters + keyword_intelligence rows. Returns
     * the rebuilt rows.
     *
     * @return Collection<int, NicheTopicCluster>
     */
    public function rebuild(Niche $niche): Collection
    {
        $clusters = KeywordCluster::query()
            ->whereHas('keywords', fn ($q) => $q->whereIn('keywords.id', function ($sub) use ($niche) {
                $sub->select('keyword_id')
                    ->from('niche_keyword_map')
                    ->where('niche_id', $niche->id);
            }))
            ->get();

        $rebuilt = collect();

        foreach ($clusters as $cluster) {
            $stats = $this->statsForCluster($cluster);
            if ($stats['total_volume'] === 0) {
                continue;
            }

            $row = NicheTopicCluster::updateOrCreate(
                ['niche_id' => $niche->id, 'cluster_id' => $cluster->id],
                [
                    'topic_name' => $cluster->cluster_name,
                    'total_search_volume' => $stats['total_volume'],
                    'avg_difficulty' => $stats['avg_difficulty'],
                    'priority_score' => $this->priority($stats),
                ]
            );

            $rebuilt->push($row);
        }

        return $rebuilt;
    }

    /**
     * @return array{total_volume:int, avg_difficulty:float, count:int}
     */
    private function statsForCluster(KeywordCluster $cluster): array
    {
        $intel = KeywordIntelligence::query()
            ->whereIn('keyword_id', $cluster->keywords()->pluck('keywords.id'))
            ->get();

        $total = (int) $intel->sum('search_volume');
        $diffs = $intel->pluck('difficulty_score')->filter(fn ($v) => $v !== null);

        return [
            'total_volume' => $total,
            'avg_difficulty' => $diffs->isEmpty() ? 0.0 : (float) round($diffs->avg(), 2),
            'count' => $intel->count(),
        ];
    }

    /** @param array{total_volume:int, avg_difficulty:float, count:int} $stats */
    private function priority(array $stats): float
    {
        $difficulty = max(1.0, $stats['avg_difficulty']);

        return round($stats['total_volume'] / $difficulty, 2);
    }
}
