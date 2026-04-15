<?php

namespace Tests\Feature;

use App\Mail\GrowthReportMail;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendGrowthReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_one_mail_per_website_to_owner(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        Website::factory()->count(2)->create(['user_id' => $owner->id]);

        $this->artisan('growthhub:send-reports')->assertSuccessful();

        $queued = Mail::queued(GrowthReportMail::class);
        $this->assertCount(2, $queued);
        foreach ($queued as $mail) {
            $this->assertTrue($mail->user->is($owner));
        }
    }

    public function test_command_does_not_queue_when_no_websites(): void
    {
        Mail::fake();

        $this->artisan('growthhub:send-reports')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
