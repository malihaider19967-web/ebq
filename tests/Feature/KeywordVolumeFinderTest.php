<?php

namespace Tests\Feature;

use App\Livewire\Keywords\KeywordVolumeFinder;
use App\Models\KeywordMetric;
use App\Models\User;
use App\Services\Usage\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class KeywordVolumeFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.keywords_everywhere.key' => 'k',
            'services.keywords_everywhere.base_url' => 'https://api.keywordseverywhere.com',
            'services.keywords_everywhere.fresh_days' => 30,
        ]);
        $this->actingAs(User::factory()->create());
    }

    /** Bind a UsageMeter whose quota answers are fully controlled by the test. */
    private function fakeMeter(?int $remaining, ?int $limit = null): void
    {
        $meter = new class extends UsageMeter
        {
            public ?int $rem = null;

            public ?int $lim = null;

            public function remaining(User $user, string $provider): ?int
            {
                return $this->rem;
            }

            public function limit(User $user, string $provider): ?int
            {
                return $this->lim;
            }
        };
        $meter->rem = $remaining;
        $meter->lim = $limit;
        $this->app->instance(UsageMeter::class, $meter);
    }

    public function test_quota_preflight_blocks_when_too_few_credits(): void
    {
        Http::fake();
        $this->fakeMeter(remaining: 1, limit: 5);

        Livewire::test(KeywordVolumeFinder::class)
            ->set('keywords', "alpha\nbravo")   // 2 uncached keywords, only 1 credit
            ->set('country', 'global')
            ->call('run')
            ->assertSet('hasRun', false)
            ->assertSee('left this month');

        Http::assertNothingSent();
    }

    public function test_cached_keywords_are_free_even_at_zero_quota(): void
    {
        Http::fake();
        $this->fakeMeter(remaining: 0, limit: 5);

        KeywordMetric::create([
            'keyword' => 'alpha',
            'keyword_hash' => KeywordMetric::hashKeyword('alpha'),
            'country' => 'global',
            'data_source' => 'gkp',
            'search_volume' => 5400,
            'cpc' => 2.0,
            'currency' => 'USD',
            'competition' => 0.2,
            'trend_12m' => null,
            'fetched_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDays(29),
        ]);

        Livewire::test(KeywordVolumeFinder::class)
            ->set('keywords', 'alpha')
            ->set('country', 'global')
            ->call('run')
            ->assertSet('hasRun', true)
            ->assertSee('5,400');

        Http::assertNothingSent();
    }

    public function test_uncached_keyword_is_fetched_and_cached(): void
    {
        Http::fake([
            'api.keywordseverywhere.com/*' => Http::response([
                'data' => [[
                    'keyword' => 'charlie',
                    'vol' => 1234,
                    'cpc' => ['currency' => 'USD', 'value' => 2.5],
                    'competition' => 0.4,
                    'trend' => [['month' => 1, 'year' => 2026, 'value' => 900]],
                ]],
                'credits' => 9999,
            ], 200),
        ]);
        $this->fakeMeter(remaining: 10, limit: 10);

        Livewire::test(KeywordVolumeFinder::class)
            ->set('keywords', 'charlie')
            ->set('country', 'global')
            ->call('run')
            ->assertSet('hasRun', true)
            ->assertSee('1,234');

        $this->assertDatabaseHas('keyword_metrics', [
            'keyword_hash' => KeywordMetric::hashKeyword('charlie'),
            'country' => 'global',
            'search_volume' => 1234,
        ]);
    }

    public function test_empty_input_is_rejected(): void
    {
        $this->fakeMeter(remaining: 10, limit: 10);

        Livewire::test(KeywordVolumeFinder::class)
            ->set('keywords', '   ')
            ->call('run')
            ->assertSet('hasRun', false)
            ->assertSee('at least one keyword');
    }
}
