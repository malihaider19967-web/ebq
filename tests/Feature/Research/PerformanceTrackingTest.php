<?php

namespace Tests\Feature\Research;

use App\Livewire\Research\PerformanceTracking;
use App\Models\Research\Keyword;
use App\Models\Research\Niche;
use App\Models\Research\NicheAggregate;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_flags_pages_below_niche_benchmark(): void
    {
        (new NicheTaxonomySeeder())->run();
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        session(['current_website_id' => $website->id]);

        $kw = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor('matcha latte'), 'country' => 'us', 'language' => 'en'],
            ['query' => 'matcha latte', 'normalized_query' => 'matcha latte']
        );
        $niche = Niche::query()->where('slug', 'recipes')->firstOrFail();
        $niche->keywords()->attach($kw->id, ['relevance_score' => 0.8]);

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDays(7)->toDateString(),
            'query' => 'matcha latte',
            'page' => 'https://site.test/matcha',
            'clicks' => 1,
            'impressions' => 1000,
            'position' => 3.0,
            'ctr' => 0.001,
            'country' => 'USA',
            'device' => '',
            'keyword_id' => $kw->id,
        ]);

        // Niche benchmark says position 3 should yield ~11% CTR — site is at 0.1%.
        // Sample floor of 3 must be met to surface.
        NicheAggregate::create([
            'niche_id' => $niche->id,
            'keyword_id' => null,
            'avg_ctr_by_position' => ['3' => 0.11],
            'sample_site_count' => 5,
        ]);

        $component = Livewire::test(PerformanceTracking::class);
        $rows = $component->get('rows') ?? collect();

        $component->assertViewHas('rows', function ($rows) {
            $row = $rows->first();
            return $row !== null
                && $row['benchmark'] === 0.11
                && $row['underperforming'] === true;
        });
    }

    public function test_route_renders_for_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = Website::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        session(['current_website_id' => $website->id]);

        $this->get(route('research.performance'))->assertOk();
    }
}
