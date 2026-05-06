<?php

namespace Tests\Feature\Research;

use App\Models\Research\Keyword;
use App\Models\Research\Niche;
use App\Models\Research\WebsitePage;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResearchBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedGsc(int $websiteId, string $query, string $page, string $country = 'USA'): void
    {
        SearchConsoleData::create([
            'website_id' => $websiteId,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => $query,
            'page' => $page,
            'clicks' => 1,
            'impressions' => 100,
            'position' => 8.0,
            'ctr' => 0.01,
            'country' => $country,
            'device' => '',
        ]);
    }

    public function test_backfill_seeds_niches_keywords_and_pages(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedGsc($website->id, 'best running shoes', 'https://site.test/running');
        $this->seedGsc($website->id, 'trail runners review', 'https://site.test/trail');

        $this->artisan('ebq:research-backfill', ['--website' => $website->id])
            ->assertSuccessful();

        $this->assertGreaterThan(0, Niche::count());
        $this->assertSame(2, Keyword::count());
        $this->assertSame(2, WebsitePage::query()->where('website_id', $website->id)->count());
        $this->assertSame(0, SearchConsoleData::query()->whereNull('keyword_id')->count());
    }

    public function test_dry_run_does_not_write(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedGsc($website->id, 'matcha latte', 'https://site.test/matcha');

        $this->artisan('ebq:research-backfill', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Keyword::count());
        $this->assertSame(0, WebsitePage::count());
    }

    public function test_backfill_is_idempotent(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->seedGsc($website->id, 'matcha latte', 'https://site.test/matcha');

        $this->artisan('ebq:research-backfill', ['--website' => $website->id])->assertSuccessful();
        $afterFirst = [Keyword::count(), WebsitePage::count(), Niche::count()];

        $this->artisan('ebq:research-backfill', ['--website' => $website->id])->assertSuccessful();
        $afterSecond = [Keyword::count(), WebsitePage::count(), Niche::count()];

        $this->assertSame($afterFirst, $afterSecond);
    }
}
