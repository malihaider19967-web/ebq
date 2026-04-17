<?php

namespace Tests\Feature;

use App\Models\PageAuditReport;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAuditDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_audit_for_foreign_website(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Website::factory()->create(['user_id' => $intruder->id]);
        $website = Website::factory()->create(['user_id' => $owner->id]);
        $report = PageAuditReport::query()->create([
            'website_id' => $website->id,
            'page' => 'https://'.$website->domain.'/x',
            'page_hash' => hash('sha256', 'https://'.$website->domain.'/x'),
            'status' => 'completed',
            'audited_at' => now(),
            'http_status' => 200,
            'response_time_ms' => 50,
            'page_size_bytes' => 1000,
            'error_message' => null,
            'result' => ['metadata' => ['title' => 'X']],
        ]);

        $this->actingAs($intruder)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('page-audits.show', $report))
            ->assertForbidden();
    }
}
