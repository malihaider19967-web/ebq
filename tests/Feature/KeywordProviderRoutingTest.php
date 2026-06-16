<?php

namespace Tests\Feature;

use App\Models\KeywordApiRequest;
use App\Models\KeywordApiServer;
use App\Services\KeywordMetricsService;
use App\Support\KeywordProviderConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeywordProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_finder_provider_dispatches_async_instead_of_calling_ke(): void
    {
        KeywordProviderConfig::setProvider(KeywordProviderConfig::PROVIDER_KEYWORD_FINDER);
        config(['services.keywords_everywhere.key' => 'should-not-be-used']);

        KeywordApiServer::create([
            'name' => 'A',
            'base_url' => 'http://a.test',
            'api_key' => 'k',
            'webhook_secret' => 's',
            'is_active' => true,
        ]);

        Http::fake([
            'a.test/*' => Http::response(['queued' => true], 200),
            // Guard: KE must never be hit on this path.
            '*keywordseverywhere.com*' => Http::response(['data' => []], 200),
        ]);

        $written = app(KeywordMetricsService::class)->refresh(['seo audit'], 'us');

        // Async path writes nothing synchronously.
        $this->assertSame(0, $written);
        // It created a tracked IDEAS request (seed expansion warms the cache
        // far beyond the asked-for keyword), tagged with the country to cache
        // results under, pointed at our server — and never touched KE.
        $req = KeywordApiRequest::query()->latest('id')->first();
        $this->assertNotNull($req);
        $this->assertSame(KeywordApiRequest::TYPE_IDEAS, $req->type);
        $this->assertSame(['seo audit'], $req->payload['seeds']);
        $this->assertSame('us', $req->payload['country_key']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'keywordseverywhere.com'));
    }

    public function test_default_provider_still_uses_keywords_everywhere(): void
    {
        // No provider set → defaults to KE.
        config(['services.keywords_everywhere.key' => 'test-key']);

        Http::fake([
            '*keywordseverywhere.com*' => Http::response([
                'data' => [['keyword' => 'seo', 'vol' => 100, 'cpc' => ['value' => 1, 'currency' => 'USD'], 'competition' => 0.2]],
                'credits' => 1,
            ], 200),
        ]);

        $written = app(KeywordMetricsService::class)->refresh(['seo'], 'global');

        $this->assertSame(1, $written);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'keywordseverywhere.com'));
    }
}
