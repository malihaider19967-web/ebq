<?php

namespace Tests\Feature;

use App\Models\CustomPageAudit;
use App\Models\PageAuditReport;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageDetailAuditRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_detail_lists_audit_runs_for_that_url(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $pageUrl = 'https://'.$website->domain.'/docs/guide';

        $report = PageAuditReport::query()->create([
            'website_id' => $website->id,
            'page' => mb_substr($pageUrl, 0, 700),
            'page_hash' => hash('sha256', $pageUrl),
            'status' => 'completed',
            'audited_at' => now(),
            'http_status' => 200,
            'response_time_ms' => 50,
            'page_size_bytes' => 2000,
            'error_message' => null,
            'result' => ['metadata' => ['title' => 'Guide']],
        ]);

        CustomPageAudit::recordRun(
            $website->id,
            $user->id,
            $pageUrl,
            $report,
            null,
            CustomPageAudit::SOURCE_PAGE_DETAIL,
        );

        $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('pages.show', ['id' => rawurlencode($pageUrl)]))
            ->assertOk()
            ->assertSee('Page audits', false)
            ->assertSee('SERP keyword', false);
    }
}
