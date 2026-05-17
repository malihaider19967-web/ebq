<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Services\PageAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PageAuditHqApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hq_page_audit_suggestions_requires_valid_url_on_site(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'domain' => 'example.test',
        ]);
        $token = $website->createToken('test', ['read:insights'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/hq/page-audit/suggestions?page_url=https://other.test/page')
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_domain');
    }

    public function test_hq_page_audit_queue_returns_needs_country_then_queues(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'domain' => 'example.test',
        ]);
        $token = $website->createToken('test', ['read:insights'])->plainTextToken;
        $pageUrl = 'https://example.test/landing';

        $peek = Mockery::mock(PageAuditService::class);
        $peek->shouldReceive('peekSerpCountryChoiceNeeded')
            ->once()
            ->with($website->id, $pageUrl, true)
            ->andReturn([
                'ok' => true,
                'recommended_gl' => 'gb',
                'recommendation_hint' => 'Page language suggests United Kingdom.',
            ]);
        $this->app->instance(PageAuditService::class, $peek);

        $this->withToken($token)
            ->postJson('/api/v1/hq/page-audit', [
                'page_url' => $pageUrl,
                'target_keyword' => 'widgets',
            ])
            ->assertOk()
            ->assertJsonPath('needs_country', true)
            ->assertJsonPath('recommended_gl', 'gb');

        $peek2 = Mockery::mock(PageAuditService::class);
        $this->app->instance(PageAuditService::class, $peek2);

        $this->withToken($token)
            ->postJson('/api/v1/hq/page-audit', [
                'page_url' => $pageUrl,
                'target_keyword' => 'widgets',
                'confirm_country' => true,
                'serp_country_gl' => 'gb',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['audit' => ['id', 'status', 'page_url', 'target_keyword']]);

        $this->assertDatabaseHas('custom_page_audits', [
            'website_id' => $website->id,
            'page_url' => $pageUrl,
            'target_keyword' => 'widgets',
            'serp_sample_gl' => 'gb',
            'source' => 'hq_wp',
        ]);
    }

    public function test_hq_page_audits_lists_recent_rows(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $token = $website->createToken('test', ['read:insights'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/hq/page-audits')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['audits', 'has_pending']);
    }
}
