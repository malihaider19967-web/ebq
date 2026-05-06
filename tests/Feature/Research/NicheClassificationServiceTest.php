<?php

namespace Tests\Feature\Research;

use App\Models\Research\Niche;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use App\Services\Research\Niche\NicheClassificationService;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NicheClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(LlmClient::class, fn () => new class implements LlmClient {
            public function complete(array $messages, array $options = []): array
            {
                return ['ok' => true, 'content' => '', 'model' => 'fake', 'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0]];
            }
            public function completeJson(array $messages, array $options = []): ?array
            {
                return null;
            }
            public function isAvailable(): bool
            {
                return false;
            }
            public function completeWithTools(array $messages, array $tools, callable $dispatcher, array $options = []): array
            {
                return ['ok' => false, 'decoded' => null, 'content' => '', 'model' => 'fake', 'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0], 'tool_calls' => []];
            }
        });
    }

    private function seedGsc(int $websiteId, string $query, int $impressions): void
    {
        SearchConsoleData::create([
            'website_id' => $websiteId,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => $query,
            'page' => 'https://site.test/'.md5($query),
            'clicks' => max(1, (int) ($impressions * 0.05)),
            'impressions' => $impressions,
            'position' => 8.0,
            'ctr' => 0.05,
            'country' => 'USA',
            'device' => '',
        ]);
    }

    public function test_classification_picks_running_as_primary(): void
    {
        (new NicheTaxonomySeeder())->run();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedGsc($website->id, 'best running shoes', 5000);
        $this->seedGsc($website->id, 'trail running shoes', 3000);
        $this->seedGsc($website->id, 'running form tips', 1500);

        $service = app(NicheClassificationService::class);

        $assignments = $service->classify($website);
        $this->assertGreaterThan(0, $assignments->count());

        $primary = $assignments->firstWhere('is_primary', true);
        $this->assertNotNull($primary);

        $primaryNiche = Niche::query()->find($primary['niche_id']);
        $this->assertNotNull($primaryNiche);
        $this->assertSame('running', $primaryNiche->slug, 'Expected running to win on GSC token overlap.');
    }

    public function test_persist_writes_website_niche_map_rows(): void
    {
        (new NicheTaxonomySeeder())->run();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedGsc($website->id, 'easy recipes for dinner', 5000);

        $service = app(NicheClassificationService::class);
        $assignments = $service->classify($website);

        $service->persist($website, $assignments, 'auto');

        $this->assertGreaterThan(0, $website->niches()->count());
        $primary = $website->niches()->wherePivot('is_primary', true)->first();
        $this->assertNotNull($primary);
    }

    public function test_classification_returns_empty_without_signals(): void
    {
        (new NicheTaxonomySeeder())->run();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $service = app(NicheClassificationService::class);
        $this->assertSame(0, $service->classify($website)->count());
    }
}
