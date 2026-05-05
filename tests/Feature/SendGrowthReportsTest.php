<?php

namespace Tests\Feature;

use App\Mail\GrowthReportMail;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendGrowthReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_one_mail_per_website_to_owner(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $websites = Website::factory()->count(2)->create(['user_id' => $owner->id]);

        $safeDate = Carbon::now()->subDays(5)->toDateString();
        foreach ($websites as $website) {
            SearchConsoleData::create([
                'website_id' => $website->id,
                'date' => $safeDate,
                'query' => 'seed',
                'page' => 'https://example.com/',
                'clicks' => 1,
                'impressions' => 10,
                'position' => 5.0,
                'ctr' => 0.1,
            ]);
        }

        $this->artisan('ebq:send-reports')->assertSuccessful();

        $queued = Mail::queued(GrowthReportMail::class);
        $this->assertCount(2, $queued);
        foreach ($queued as $mail) {
            $this->assertTrue($mail->user->is($owner));
        }
    }

    public function test_command_skips_sites_with_no_search_console_data(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        Website::factory()->create(['user_id' => $owner->id]);

        $this->artisan('ebq:send-reports')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_command_does_not_queue_when_no_websites(): void
    {
        Mail::fake();

        $this->artisan('ebq:send-reports')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
