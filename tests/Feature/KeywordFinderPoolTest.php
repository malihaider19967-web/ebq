<?php

namespace Tests\Feature;

use App\Models\KeywordApiRequest;
use App\Models\KeywordApiServer;
use App\Services\KeywordFinder\KeywordFinderPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeywordFinderPoolTest extends TestCase
{
    use RefreshDatabase;

    private function makeServer(array $overrides = []): KeywordApiServer
    {
        return KeywordApiServer::create(array_merge([
            'name' => 'Server A',
            'base_url' => 'http://server-a.test',
            'api_key' => 'key-a',
            'webhook_secret' => 'secret-a',
            'is_active' => true,
        ], $overrides));
    }

    public function test_dispatch_volume_creates_running_request_on_ack(): void
    {
        $server = $this->makeServer();
        Http::fake(['server-a.test/*' => Http::response(['queued' => true], 200)]);

        $request = app(KeywordFinderPool::class)->dispatchVolume(['seo audit'], 'us');

        $this->assertSame(KeywordApiRequest::STATUS_RUNNING, $request->status);
        $this->assertSame($server->id, $request->keyword_api_server_id);
        $this->assertSame('us', $request->payload['country_key']);
        $this->assertNotNull($request->dispatched_at);
    }

    public function test_fails_friendly_when_no_servers(): void
    {
        $request = app(KeywordFinderPool::class)->dispatchVolume(['seo audit'], 'us');

        $this->assertSame(KeywordApiRequest::STATUS_FAILED, $request->status);
        $this->assertStringContainsString('available', $request->error);
    }

    public function test_transient_failure_fails_over_to_next_server(): void
    {
        // Lower queue depth wins, so server A is tried first and 500s; B succeeds.
        $this->makeServer(['name' => 'A', 'base_url' => 'http://a.test', 'last_queue_waiting' => 0]);
        $b = $this->makeServer(['name' => 'B', 'base_url' => 'http://b.test', 'last_queue_waiting' => 1]);

        Http::fake([
            'a.test/*' => Http::response(['error' => 'boom'], 500),
            'b.test/*' => Http::response(['queued' => true], 200),
        ]);

        $request = app(KeywordFinderPool::class)->dispatchVolume(['kw'], 'global');

        $this->assertSame(KeywordApiRequest::STATUS_RUNNING, $request->status);
        $this->assertSame($b->id, $request->keyword_api_server_id);
    }

    public function test_permanent_error_stops_cascade_and_flags_server(): void
    {
        $a = $this->makeServer(['name' => 'A', 'base_url' => 'http://a.test']);
        $this->makeServer(['name' => 'B', 'base_url' => 'http://b.test']);

        Http::fake([
            'a.test/*' => Http::response(['error' => 'unauthorized'], 401),
            'b.test/*' => Http::response(['queued' => true], 200),
        ]);

        $request = app(KeywordFinderPool::class)->dispatchVolume(['kw'], 'global', only: $a);

        $this->assertSame(KeywordApiRequest::STATUS_FAILED, $request->status);
        // 401 → server flagged unhealthy, never leaks raw "unauthorized".
        $this->assertStringNotContainsString('unauthorized', strtolower($request->error));
        $this->assertFalse($a->fresh()->is_healthy);
    }

    public function test_unhealthy_servers_are_skipped_by_router(): void
    {
        $this->makeServer(['name' => 'dead', 'base_url' => 'http://dead.test', 'is_healthy' => false]);
        $healthy = $this->makeServer(['name' => 'live', 'base_url' => 'http://live.test', 'is_healthy' => true]);

        Http::fake([
            'dead.test/*' => Http::response([], 500),
            'live.test/*' => Http::response(['queued' => true], 200),
        ]);

        $request = app(KeywordFinderPool::class)->dispatchVolume(['kw'], 'global');

        $this->assertSame($healthy->id, $request->keyword_api_server_id);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'dead.test'));
    }
}
