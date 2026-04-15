<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\SyncAndReportPanel;
use App\Mail\GrowthReportMail;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SyncAndReportPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_report_dispatches_growth_report_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        session(['current_website_id' => $website->id]);

        Livewire::actingAs($user)
            ->test(SyncAndReportPanel::class)
            ->call('sendReport')
            ->assertSee('Report sent');

        Mail::assertSent(GrowthReportMail::class, function (GrowthReportMail $mail) use ($user, $website) {
            return $mail->user->is($user) && $mail->website->is($website);
        });
    }

    public function test_send_report_is_rate_limited_after_five_sends_per_hour(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        session(['current_website_id' => $website->id]);

        RateLimiter::clear('send-growth-report:'.$user->id);

        $component = Livewire::actingAs($user)->test(SyncAndReportPanel::class);

        for ($i = 0; $i < 5; $i++) {
            $component->call('sendReport');
        }

        $component->call('sendReport')
            ->assertSee('Too many attempts');
    }
}
