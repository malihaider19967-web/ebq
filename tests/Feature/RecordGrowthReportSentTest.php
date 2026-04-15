<?php

namespace Tests\Feature;

use App\Mail\GrowthReportMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecordGrowthReportSentTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sent_updates_user_timestamp(): void
    {
        $user = User::factory()->create();

        Mail::to($user->email)->send(new GrowthReportMail($user));

        $user->refresh();
        $this->assertNotNull($user->last_growth_report_sent_at);
    }
}
