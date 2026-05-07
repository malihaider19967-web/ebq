<?php

namespace App\Jobs\Research;

use App\Models\Research\Keyword;
use App\Models\Research\KeywordCluster;
use App\Models\Research\Niche;
use App\Services\Research\ClusteringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Find keywords that no curated niche fits well, cluster them, and seed
 * dynamic niche candidates the admin can promote into the curated tree.
 *
 * Match criteria for "no good fit": every niche_keyword_map row has
 * relevance_score < 0.2, OR no niche_keyword_map row exists at all.
 *
 * Min cluster size for promotion: configurable via the constructor;
 * defaults to 5. Persistence-across-N-runs gating is left for a future
 * upgrade (would need a counter column); today every cluster meeting
 * the size threshold becomes a candidate immediately, with the admin
 * acting as the final gate via /admin/research/niche-candidates.
 */
class DiscoverEmergingNichesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(
        public int $minClusterSize = 5,
        public int $candidatesPerRun = 20,
    ) {}

    public function handle(ClusteringService $clustering): void
    {
        $unmatched = $this->findUnmatchedKeywords();
        if ($unmatched->isEmpty()) {
            Log::info('DiscoverEmergingNichesJob: no unmatched keywords this run.');

            return;
        }

        $clusters = $clustering->cluster($unmatched);
        $created = 0;

        foreach ($clusters as $cluster) {
            if ($created >= $this->candidatesPerRun) {
                break;
            }
            if ($cluster->keywords()->count() < $this->minClusterSize) {
                continue;
            }
            if ($this->candidateFor($cluster) !== null) {
                $created++;
            }
        }

        Log::info("DiscoverEmergingNichesJob: created {$created} candidate niche(s).");
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Keyword> */
    private function findUnmatchedKeywords(): \Illuminate\Database\Eloquent\Collection
    {
        $maxRelevanceByKeyword = DB::table('niche_keyword_map')
            ->select('keyword_id')
            ->selectRaw('MAX(relevance_score) as max_relevance')
            ->groupBy('keyword_id');

        return Keyword::query()
            ->leftJoinSub($maxRelevanceByKeyword, 'mr', 'mr.keyword_id', '=', 'keywords.id')
            ->whereRaw('(mr.max_relevance IS NULL OR mr.max_relevance < 0.2)')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('serp_snapshots')
                    ->whereColumn('serp_snapshots.keyword_id', 'keywords.id');
            })
            ->select('keywords.*')
            ->limit(2000)
            ->get();
    }

    private function candidateFor(KeywordCluster $cluster): ?Niche
    {
        $centroid = $cluster->centroid;
        if ($centroid === null) {
            return null;
        }

        $slug = 'dyn-'.Str::slug($centroid->normalized_query);
        if ($slug === 'dyn-' || mb_strlen($slug) > 128) {
            return null;
        }

        $existing = Niche::query()->where('slug', $slug)->first();
        if ($existing !== null) {
            return null;
        }

        return Niche::create([
            'slug' => $slug,
            'name' => ucfirst(mb_substr($centroid->normalized_query, 0, 64)),
            'parent_id' => null,
            'is_dynamic' => true,
            'is_approved' => false,
        ]);
    }
}
