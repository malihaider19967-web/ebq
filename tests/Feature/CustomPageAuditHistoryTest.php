<?php

namespace Tests\Feature;

use App\Models\CustomPageAudit;
use App\Models\PageAuditReport;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_audit_page_lists_logged_audits_for_current_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $pageUrl = 'https://'.$website->domain.'/blog/post';
        $report = PageAuditReport::query()->create([
            'website_id' => $website->id,
            'page' => mb_substr($pageUrl, 0, 700),
            'page_hash' => hash('sha256', $pageUrl),
            'primary_keyword' => 'unique serp phrase xyz',
            'primary_keyword_source' => 'custom_audit',
            'status' => 'completed',
            'audited_at' => now(),
            'http_status' => 200,
            'response_time_ms' => 100,
            'page_size_bytes' => 5000,
            'error_message' => null,
            'result' => ['metadata' => ['title' => 'T']],
        ]);

        CustomPageAudit::recordRun(
            $website->id,
            $user->id,
            $pageUrl,
            $report,
            'unique serp phrase xyz',
            CustomPageAudit::SOURCE_CUSTOM,
        );

        $response = $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('custom-audit.index'));

        $response->assertOk();
        $response->assertSee('unique serp phrase xyz', false);
        $response->assertSee('Recent audits', false);

        $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('page-audits.show', $report))
            ->assertOk()
            ->assertSee('unique serp phrase xyz', false)
            ->assertSee('Primary keyword', false)
            ->assertSee('Page audit', false);
    }

    public function test_custom_audit_page_lists_wordpress_hq_audits(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $pageUrl = 'https://'.$website->domain.'/from-wp';

        CustomPageAudit::queue(
            websiteId: $website->id,
            userId: $user->id,
            pageUrl: $pageUrl,
            targetKeyword: 'wp audit keyword',
            serpSampleGl: 'us',
            source: CustomPageAudit::SOURCE_HQ_WP,
        );

        $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('custom-audit.index'))
            ->assertOk()
            ->assertSee('wp audit keyword', false)
            ->assertSee('WordPress', false);
    }
}
