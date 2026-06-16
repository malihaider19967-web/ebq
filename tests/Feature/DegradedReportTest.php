<?php

namespace Tests\Feature;

use App\Mail\GrowthReportMail;
use App\Models\AnalyticsData;
use App\Models\User;
use App\Models\Website;
use App\Services\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DegradedReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_reports_per_source_connection_state(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withGaOnly()->create(['user_id' => $user->id]);

        $report = app(ReportDataService::class)->generate(
            $website->id,
            Carbon::now()->subDays(7)->toDateString(),
            Carbon::now()->toDateString(),
        );

        $this->assertTrue($report['sources']['ga']);
        $this->assertFalse($report['sources']['gsc']);
        // GSC sections still resolve (empty), they don't blow up.
        $this->assertIsArray($report['search_console']);
    }

    public function test_ga_only_site_with_analytics_data_gets_a_degraded_email(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $website = Website::factory()->withGaOnly()->create(['user_id' => $owner->id]);

        AnalyticsData::create([
            'website_id' => $website->id,
            'date' => Carbon::now()->subDays(2)->toDateString(),
            'source' => '(direct)',
            'users' => 10,
            'sessions' => 14,
            'bounce_rate' => 40.0,
        ]);

        $this->artisan('ebq:send-reports')->assertSuccessful();

        Mail::assertQueued(GrowthReportMail::class);
    }

    public function test_site_with_no_sources_is_skipped(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        Website::factory()->withNoSources()->create(['user_id' => $owner->id]);

        $this->artisan('ebq:send-reports')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
