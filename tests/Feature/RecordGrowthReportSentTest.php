<?php

namespace Tests\Feature;

use App\Mail\GrowthReportMail;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecordGrowthReportSentTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sent_updates_user_timestamp(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $date = Carbon::now()->subDays(5)->toDateString();

        Mail::to($user->email)->send(new GrowthReportMail($user, $website, $date, $date));

        $user->refresh();
        $this->assertNotNull($user->last_growth_report_sent_at);
    }
}
