<?php

namespace Tests\Feature;

use App\Livewire\Keywords\KeywordIdeaFinder;
use App\Models\KeywordApiRequest;
use App\Models\KeywordApiServer;
use App\Models\User;
use App\Support\KeywordProviderConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class KeywordIdeaFinderCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        KeywordProviderConfig::setProvider(KeywordProviderConfig::PROVIDER_KEYWORD_FINDER);
        KeywordApiServer::create([
            'name' => 'Server A', 'base_url' => 'http://server-a.test',
            'api_key' => 'key-a', 'webhook_secret' => 'secret-a', 'is_active' => true,
        ]);
    }

    public function test_second_user_searching_the_same_seeds_this_month_gets_an_instant_cached_result(): void
    {
        Http::fake(['server-a.test/*' => Http::response(['queued' => true], 200)]);

        $first = User::factory()->create(['email_verified_at' => now()]);
        $component = Livewire::actingAs($first)
            ->test(KeywordIdeaFinder::class)
            ->set('seedsInput', 'seo audit')
            ->call('run');

        $request = KeywordApiRequest::query()->latest('id')->first();
        $this->assertNotNull($request);

        // Simulate the webhook completing the lookup.
        $request->markCompleted(['results' => [
            ['keyword' => 'seo audit tool', 'volume' => 100, 'competitionIndex' => 'low', 'cpc' => 1.2],
        ]]);
        $component->call('poll')
            ->assertSet('fromCache', false)
            ->assertSee('seo audit tool');

        // A second, different user searches the exact same seed — must NOT
        // dispatch a new request (no new queue row) and must be served from
        // the shared monthly cache instantly.
        $countBefore = KeywordApiRequest::query()->count();
        $second = User::factory()->create(['email_verified_at' => now()]);
        Livewire::actingAs($second)
            ->test(KeywordIdeaFinder::class)
            ->set('seedsInput', 'SEO Audit') // different casing/whitespace — must still hit
            ->call('run')
            ->assertSet('fromCache', true)
            ->assertSee('seo audit tool');

        $this->assertSame($countBefore, KeywordApiRequest::query()->count(), 'expected no new dispatch for a cached lookup');
    }

    public function test_cache_is_ignored_once_the_calendar_month_changes(): void
    {
        Http::fake(['server-a.test/*' => Http::response(['queued' => true], 200)]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $component = Livewire::actingAs($user)
            ->test(KeywordIdeaFinder::class)
            ->set('seedsInput', 'seo audit')
            ->call('run');

        $request = KeywordApiRequest::query()->latest('id')->first();
        $request->markCompleted(['results' => [['keyword' => 'seo audit tool', 'volume' => 100, 'competitionIndex' => 'low', 'cpc' => 1.2]]]);
        $component->call('poll');

        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDay());

        $countBefore = KeywordApiRequest::query()->count();
        Livewire::actingAs($user)
            ->test(KeywordIdeaFinder::class)
            ->set('seedsInput', 'seo audit')
            ->call('run')
            ->assertSet('fromCache', false);

        $this->assertSame($countBefore + 1, KeywordApiRequest::query()->count(), 'expected a fresh dispatch after the month rolled over');
    }
}
