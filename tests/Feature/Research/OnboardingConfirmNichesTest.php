<?php

namespace Tests\Feature\Research;

use App\Livewire\Onboarding\ConfirmNiches;
use App\Models\Research\Niche;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingConfirmNichesTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_is_invisible_when_no_gsc_data_yet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Website::factory()->create(['user_id' => $user->id, 'domain' => 'site.test']);

        Livewire::test(ConfirmNiches::class)->assertSet('visible', false);
    }

    public function test_component_renders_detected_niches_and_persists_on_save(): void
    {
        (new NicheTaxonomySeeder())->run();

        $user = User::factory()->create();
        $this->actingAs($user);
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'site.test']);

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'best running shoes',
            'page' => 'https://site.test/running',
            'clicks' => 10,
            'impressions' => 500,
            'position' => 8.0,
            'ctr' => 0.02,
            'country' => 'USA',
            'device' => '',
        ]);

        $component = Livewire::test(ConfirmNiches::class)->assertSet('visible', true);

        $assignments = $component->get('assignments');
        $this->assertNotEmpty($assignments);

        $component->call('save')
            ->assertSet('visible', false)
            ->assertSet('status', 'Saved.');

        $this->assertGreaterThan(0, $website->niches()->count());
        $primary = $website->niches()->wherePivot('is_primary', true)->first();
        $this->assertNotNull($primary);
        $this->assertSame('hybrid', $website->niches()->first()->pivot->source);
    }

    public function test_component_does_not_re_render_after_user_confirmation(): void
    {
        (new NicheTaxonomySeeder())->run();

        $user = User::factory()->create();
        $this->actingAs($user);
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'site.test']);

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => Carbon::today()->subDay()->toDateString(),
            'query' => 'matcha recipes',
            'page' => 'https://site.test/matcha',
            'clicks' => 10,
            'impressions' => 500,
            'position' => 5.0,
            'ctr' => 0.02,
            'country' => 'USA',
            'device' => '',
        ]);

        $niche = Niche::query()->where('slug', 'recipes')->firstOrFail();
        $website->niches()->sync([$niche->id => ['source' => 'hybrid', 'weight' => 1.0, 'is_primary' => true]]);

        Livewire::test(ConfirmNiches::class)->assertSet('visible', false);
    }
}
