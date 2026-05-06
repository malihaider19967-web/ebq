<?php

namespace Tests\Feature\Research;

use App\Models\Research\Keyword;
use App\Models\Research\KeywordCluster;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Services\Research\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClusteringServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeKeywordWithDomains(string $query, array $domains): Keyword
    {
        $keyword = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($query), 'country' => 'us', 'language' => 'en'],
            ['query' => $query, 'normalized_query' => Keyword::normalize($query)]
        );

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'device' => 'desktop',
            'country' => 'us',
            'fetched_at' => Carbon::now(),
            'fetched_on' => Carbon::today(),
        ]);

        foreach ($domains as $i => $domain) {
            SerpResult::create([
                'snapshot_id' => $snapshot->id,
                'rank' => $i + 1,
                'url' => "https://{$domain}/x",
                'domain' => $domain,
                'result_type' => 'organic',
            ]);
        }

        return $keyword;
    }

    public function test_keywords_with_overlapping_serps_land_in_one_cluster(): void
    {
        $a = $this->makeKeywordWithDomains('best running shoes', [
            'runnersworld.com', 'rei.com', 'nike.com', 'asics.com', 'salomon.com',
            'newbalance.com', 'brooksrunning.com', 'hoka.com', 'roadrunnersports.com', 'amazon.com',
        ]);
        $b = $this->makeKeywordWithDomains('top running shoes 2026', [
            'runnersworld.com', 'rei.com', 'nike.com', 'asics.com', 'newbalance.com',
            'brooksrunning.com', 'hoka.com', 'roadrunnersports.com', 'amazon.com', 'gear-junkie.test',
        ]);
        $unrelated = $this->makeKeywordWithDomains('best matcha brands', [
            'tea-blog.test', 'matcha-mag.test', 'amazon.com', 'wholefoods.com', 'specialty.test',
            'jpteaco.test', 'matchaorganics.test', 'foodnetwork.com', 'epicurious.com', 'serious-eats.test',
        ]);

        $clusters = (new ClusteringService(0.4))->cluster([$a, $b, $unrelated]);

        $this->assertGreaterThanOrEqual(2, $clusters->count());

        $bigCluster = KeywordCluster::query()
            ->whereIn('id', $clusters->pluck('id'))
            ->withCount('keywords')
            ->orderByDesc('keywords_count')
            ->first();

        $this->assertSame(2, $bigCluster->keywords_count);
        $this->assertContains(
            $bigCluster->centroid_keyword_id,
            [$a->id, $b->id],
            'Centroid should be one of the overlapping pair.'
        );
    }

    public function test_re_clustering_is_idempotent(): void
    {
        $a = $this->makeKeywordWithDomains('best blender', [
            'blenderlab.test', 'consumerreports.org', 'amazon.com', 'wirecutter.com', 'kitchengeek.test',
            'cnet.com', 'wholesomeyum.test', 'foodnetwork.com', 'reviewed.com', 'thekitchn.com',
        ]);
        $b = $this->makeKeywordWithDomains('blender for smoothies', [
            'blenderlab.test', 'consumerreports.org', 'amazon.com', 'wirecutter.com', 'thekitchn.com',
            'cnet.com', 'wholesomeyum.test', 'reviewed.com', 'gear-junkie.test', 'kitchen-mag.test',
        ]);

        $service = new ClusteringService(0.4);

        $first = $service->cluster([$a, $b]);
        $second = $service->cluster([$a, $b]);

        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
        $this->assertSame(1, KeywordCluster::query()->count());
    }
}
