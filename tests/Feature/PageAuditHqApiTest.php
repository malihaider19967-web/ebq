<?php

namespace Tests\Feature;

use App\Models\CustomPageAudit;
use App\Models\PageAuditReport;
use App\Models\User;
use App\Models\Website;
use App\Services\PageAuditService;
use App\Support\Audit\RecommendationEngine;
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

    public function test_hq_page_audit_countries_returns_serp_catalog(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $token = $website->createToken('test', ['read:insights'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/hq/page-audit/countries')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('country.recommended_gl', 'us');

        $options = $response->json('country.options');
        $this->assertIsArray($options);
        $this->assertGreaterThan(10, count($options));
        $codes = array_column($options, 'code');
        $this->assertContains('us', $codes);
        $this->assertContains('gb', $codes);
        $this->assertContains('fr', $codes);
        $us = collect($options)->firstWhere('code', 'us');
        $this->assertIsArray($us);
        $this->assertNotEmpty($us['label'] ?? '');
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

    public function test_hq_page_audits_includes_summary_for_completed_run(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'domain' => 'example.test',
        ]);
        $token = $website->createToken('test', ['read:insights'])->plainTextToken;
        $pageUrl = 'https://example.test/landing';

        $report = PageAuditReport::query()->create([
            'website_id' => $website->id,
            'page' => mb_substr($pageUrl, 0, 700),
            'page_hash' => hash('sha256', $pageUrl),
            'status' => 'completed',
            'audited_at' => now(),
            'http_status' => 200,
            'response_time_ms' => 180,
            'result' => [
                'content' => ['word_count' => 900],
                'recommendations' => [
                    ['severity' => RecommendationEngine::SEV_CRITICAL, 'title' => 'Broken canonical'],
                ],
            ],
        ]);

        $audit = CustomPageAudit::recordRun(
            $website->id,
            $user->id,
            $pageUrl,
            $report,
            'widgets',
            CustomPageAudit::SOURCE_HQ_WP,
        );
        $audit->forceFill([
            'started_at' => now()->subSeconds(42),
            'finished_at' => now(),
        ])->save();

        $this->withToken($token)
            ->getJson('/api/v1/hq/page-audits')
            ->assertOk()
            ->assertJsonPath('audits.0.id', $audit->id)
            ->assertJsonPath('audits.0.summary.score', 85)
            ->assertJsonPath('audits.0.summary.top_issue', 'Broken canonical')
            ->assertJsonPath('audits.0.summary.word_count', 900)
            ->assertJsonPath('audits.0.duration_sec', 42);
    }
}
