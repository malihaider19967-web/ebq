<?php

namespace Tests\Feature;

use App\Jobs\DetectTrafficDrops;
use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Notifications\TrafficDropAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrafficDropDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_anomalous_drop_fires_alert_once_and_dedupes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20 09:00:00', 'UTC'));
        Notification::fake();

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        // 28-day clicks baseline around 100; yesterday tanks to 10.
        for ($i = 28; $i >= 1; $i--) {
            $date = Carbon::parse('2026-04-19')->subDays($i)->toDateString();
            SearchConsoleData::create([
                'website_id' => $website->id, 'date' => $date, 'query' => 'x', 'page' => 'https://x/y',
                'clicks' => 100, 'impressions' => 2000, 'position' => 3.0, 'ctr' => 0.05,
                'country' => '', 'device' => '',
            ]);
            AnalyticsData::create([
                'website_id' => $website->id, 'date' => $date, 'source' => 'google',
                'users' => 200, 'sessions' => 300, 'bounce_rate' => 0.4,
            ]);
        }
        // Yesterday (target day) — massive drop on both metrics
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => '2026-04-19', 'query' => 'x', 'page' => 'https://x/y',
            'clicks' => 10, 'impressions' => 200, 'position' => 3.0, 'ctr' => 0.05,
            'country' => '', 'device' => '',
        ]);
        AnalyticsData::create([
            'website_id' => $website->id, 'date' => '2026-04-19', 'source' => 'google',
            'users' => 20, 'sessions' => 30, 'bounce_rate' => 0.4,
        ]);

        (new DetectTrafficDrops($website->id))->handle(app(\App\Services\TrafficAnomalyDetector::class));

        Notification::assertSentToTimes($user, TrafficDropAlert::class, 1);

        $website->refresh();
        $this->assertNotNull($website->last_traffic_drop_alert_at);

        // Re-run immediately — dedupe kicks in, no new notification.
        (new DetectTrafficDrops($website->id))->handle(app(\App\Services\TrafficAnomalyDetector::class));
        Notification::assertSentToTimes($user, TrafficDropAlert::class, 1);
    }

    public function test_no_alert_when_metrics_are_stable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20 09:00:00', 'UTC'));
        Notification::fake();

        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        for ($i = 28; $i >= 0; $i--) {
            $date = Carbon::parse('2026-04-19')->subDays($i)->toDateString();
            SearchConsoleData::create([
                'website_id' => $website->id, 'date' => $date, 'query' => 'x', 'page' => 'https://x/y',
                'clicks' => 100, 'impressions' => 2000, 'position' => 3.0, 'ctr' => 0.05,
                'country' => '', 'device' => '',
            ]);
            AnalyticsData::create([
                'website_id' => $website->id, 'date' => $date, 'source' => 'google',
                'users' => 200, 'sessions' => 300, 'bounce_rate' => 0.4,
            ]);
        }

        (new DetectTrafficDrops($website->id))->handle(app(\App\Services\TrafficAnomalyDetector::class));
        Notification::assertNothingSent();
    }
}
