<?php

namespace Tests\Feature;

use App\Models\KeywordApiRequest;
use App\Models\KeywordApiServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordApiQueuePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_queue_panel_shows_queued_requests_with_keyword_and_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $searcher = User::factory()->create(['name' => 'Jane Searcher', 'email_verified_at' => now()]);
        $server = KeywordApiServer::create([
            'name' => 'Server A', 'base_url' => 'http://server-a.test',
            'api_key' => 'key-a', 'webhook_secret' => 'secret-a', 'is_active' => true,
        ]);

        KeywordApiRequest::create([
            'request_id' => 'req-1', 'keyword_api_server_id' => $server->id,
            'type' => KeywordApiRequest::TYPE_IDEAS, 'mode' => 'keywords',
            'payload' => ['seeds' => ['seo audit', 'site audit'], 'location' => 'United States', 'language' => 'English'],
            'status' => KeywordApiRequest::STATUS_QUEUED, 'user_id' => $searcher->id, 'dispatched_at' => now(),
        ]);
        // Completed requests must NOT clutter the live queue.
        KeywordApiRequest::create([
            'request_id' => 'req-2', 'keyword_api_server_id' => $server->id,
            'type' => KeywordApiRequest::TYPE_IDEAS, 'mode' => 'keywords',
            'payload' => ['seeds' => ['done already']], 'status' => KeywordApiRequest::STATUS_COMPLETED,
            'user_id' => $searcher->id, 'dispatched_at' => now(), 'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.keyword-servers.index'))
            ->assertOk()
            // "1 in flight" proves the completed request was excluded by the
            // controller's status filter — only the queued one counts.
            ->assertSee('1 in flight')
            ->assertSee('Live queue')
            ->assertSee('seo audit, site audit')
            ->assertSee('Jane Searcher');
    }
}
