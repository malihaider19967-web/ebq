<?php

namespace Tests\Feature;

use App\Models\KeywordApiRequest;
use App\Models\KeywordApiServer;
use App\Models\KeywordMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordFinderWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'webhook-secret';

    private function makeRequest(string $type, array $payload = []): KeywordApiRequest
    {
        $server = KeywordApiServer::create([
            'name' => 'A',
            'base_url' => 'http://a.test',
            'api_key' => 'k',
            'webhook_secret' => $this->secret,
            'is_active' => true,
        ]);

        return KeywordApiRequest::create([
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'keyword_api_server_id' => $server->id,
            'type' => $type,
            'payload' => $payload,
            'status' => KeywordApiRequest::STATUS_RUNNING,
        ]);
    }

    private function postWebhook(string $body, ?string $signature): \Illuminate\Testing\TestResponse
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($signature !== null) {
            $headers['x-webhook-signature'] = $signature;
        }

        return $this->call('POST', '/webhooks/keyword-finder', [], [], [], $this->server($headers), $body);
    }

    /** @return array<string, string> */
    private function server(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $out['CONTENT_TYPE'] = $headers['Content-Type'] ?? 'application/json';

        return $out;
    }

    public function test_valid_signature_completes_volume_request_and_caches_metrics(): void
    {
        $request = $this->makeRequest(KeywordApiRequest::TYPE_VOLUME, ['country_key' => 'us']);

        $body = json_encode([
            'request_id' => $request->request_id,
            'result' => [
                'results' => [
                    [
                        'keyword' => 'running shoes',
                        'avgMonthlySearches' => 500000,
                        'competition' => 'High',
                        'competitionIndex' => 100,
                        'lowTopOfPageBid' => 1.68,
                        'highTopOfPageBid' => 7.38,
                    ],
                ],
            ],
        ]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->postWebhook($body, $sig)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(KeywordApiRequest::STATUS_COMPLETED, $request->fresh()->status);

        $row = KeywordMetric::where('keyword_hash', KeywordMetric::hashKeyword('running shoes'))
            ->where('country', 'us')->first();
        $this->assertNotNull($row);
        $this->assertSame(500000, $row->search_volume);
        $this->assertEqualsWithDelta(1.0, (float) $row->competition, 0.0001);
        $this->assertEqualsWithDelta(7.38, (float) $row->high_top_of_page_bid, 0.0001);
        $this->assertEqualsWithDelta(1.68, (float) $row->low_top_of_page_bid, 0.0001);
    }

    public function test_ideas_results_are_cached_for_future_lookups(): void
    {
        // A discovery (ideas) run must warm the volume cache for every keyword
        // it returns — so a later search for any of them is free.
        $request = $this->makeRequest(KeywordApiRequest::TYPE_IDEAS, ['country_key' => 'us']);

        $body = json_encode([
            'request_id' => $request->request_id,
            'status' => 'completed',
            'result' => [
                'results' => [
                    ['keyword' => 'seo audit', 'avgMonthlySearches' => 5400, 'competitionIndex' => 40, 'lowTopOfPageBid' => 2.1, 'highTopOfPageBid' => 9.5],
                    ['keyword' => 'free seo audit tool', 'avgMonthlySearches' => 1300, 'competitionIndex' => 33, 'lowTopOfPageBid' => 1.0, 'highTopOfPageBid' => 4.2],
                ],
            ],
        ]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->postWebhook($body, $sig)->assertOk();

        $this->assertSame(KeywordApiRequest::STATUS_COMPLETED, $request->fresh()->status);
        $this->assertSame(2, KeywordMetric::where('country', 'us')->count());
        $related = KeywordMetric::where('keyword_hash', KeywordMetric::hashKeyword('free seo audit tool'))
            ->where('country', 'us')->first();
        $this->assertNotNull($related);
        $this->assertSame(1300, $related->search_volume);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $request = $this->makeRequest(KeywordApiRequest::TYPE_VOLUME, ['country_key' => 'us']);
        $body = json_encode(['request_id' => $request->request_id, 'result' => ['results' => []]]);

        $this->postWebhook($body, 'deadbeef')->assertStatus(401);
        $this->assertSame(KeywordApiRequest::STATUS_RUNNING, $request->fresh()->status);
    }

    public function test_duplicate_delivery_is_idempotent(): void
    {
        $request = $this->makeRequest(KeywordApiRequest::TYPE_IDEAS);
        $request->markCompleted(['results' => [['keyword' => 'x']]]);

        $body = json_encode(['request_id' => $request->request_id, 'result' => ['results' => [['keyword' => 'y']]]]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->postWebhook($body, $sig)->assertOk()->assertJson(['duplicate' => true]);
        // Result untouched by the redelivery.
        $this->assertSame('x', $request->fresh()->result['results'][0]['keyword']);
    }

    public function test_server_side_failure_with_object_error_marks_request_failed(): void
    {
        // Real payload shape from the fleet: status=failed + error object.
        $request = $this->makeRequest(KeywordApiRequest::TYPE_VOLUME, ['country_key' => 'us']);

        $body = json_encode([
            'event' => 'keywords.volume',
            'request_id' => $request->request_id,
            'status' => 'failed',
            'error' => ['message' => 'locator.waitFor: Timeout 15000ms exceeded.', 'needsLogin' => false],
        ]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->postWebhook($body, $sig)->assertOk();

        $fresh = $request->fresh();
        $this->assertSame(KeywordApiRequest::STATUS_FAILED, $fresh->status);
        $this->assertStringContainsString('Timeout', $fresh->error);
    }

    public function test_needs_login_failure_flags_server_unhealthy(): void
    {
        $request = $this->makeRequest(KeywordApiRequest::TYPE_VOLUME, ['country_key' => 'us']);
        $serverId = $request->keyword_api_server_id;

        $body = json_encode([
            'request_id' => $request->request_id,
            'status' => 'failed',
            'error' => ['message' => 'Not logged in.', 'needsLogin' => true],
        ]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->postWebhook($body, $sig)->assertOk();

        $this->assertSame(KeywordApiRequest::STATUS_FAILED, $request->fresh()->status);
        $server = KeywordApiServer::find($serverId);
        $this->assertFalse($server->is_healthy);
        $this->assertFalse($server->logged_in);
    }

    public function test_unknown_request_id_is_404(): void
    {
        $body = json_encode(['request_id' => 'nope', 'result' => []]);
        $this->postWebhook($body, 'sig')->assertStatus(404);
    }
}
