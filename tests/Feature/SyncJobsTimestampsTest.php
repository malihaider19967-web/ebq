<?php

namespace Tests\Feature;

use App\Jobs\SyncAnalyticsData;
use App\Jobs\SyncSearchConsoleData;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Models\Website;
use App\Services\Google\GoogleAnalyticsService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class SyncJobsTimestampsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_analytics_sync_sets_timestamp_when_rows_returned(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        GoogleAccount::create([
            'user_id' => $user->id,
            'access_token' => 'test-token',
            'refresh_token' => null,
            'expires_at' => now()->addHour(),
            'google_id' => 'gid-1',
        ]);

        $mock = Mockery::mock(GoogleAnalyticsService::class);
        $mock->shouldReceive('fetchDailyTraffic')
            ->once()
            ->andReturn([
                [
                    'date' => now()->toDateString(),
                    'source' => '(direct)',
                    'users' => 1,
                    'sessions' => 2,
                    'bounce_rate' => 0.0,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ],
            ]);
        $this->app->instance(GoogleAnalyticsService::class, $mock);

        Bus::dispatchSync(new SyncAnalyticsData($website->id));

        $website->refresh();
        $this->assertNotNull($website->last_analytics_sync_at);
    }

    public function test_analytics_sync_sets_timestamp_when_rows_empty(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        GoogleAccount::create([
            'user_id' => $user->id,
            'access_token' => 'test-token',
            'refresh_token' => null,
            'expires_at' => now()->addHour(),
            'google_id' => 'gid-2',
        ]);

        $mock = Mockery::mock(GoogleAnalyticsService::class);
        $mock->shouldReceive('fetchDailyTraffic')->once()->andReturn([]);
        $this->app->instance(GoogleAnalyticsService::class, $mock);

        Bus::dispatchSync(new SyncAnalyticsData($website->id));

        $website->refresh();
        $this->assertNotNull($website->last_analytics_sync_at);
    }

    public function test_analytics_sync_does_not_set_timestamp_without_google_account(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $mock = Mockery::mock(GoogleAnalyticsService::class);
        $mock->shouldNotReceive('fetchDailyTraffic');
        $this->app->instance(GoogleAnalyticsService::class, $mock);

        Bus::dispatchSync(new SyncAnalyticsData($website->id));

        $website->refresh();
        $this->assertNull($website->last_analytics_sync_at);
    }

    public function test_search_console_sync_sets_timestamp(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        GoogleAccount::create([
            'user_id' => $user->id,
            'access_token' => 'test-token',
            'refresh_token' => null,
            'expires_at' => now()->addHour(),
            'google_id' => 'gid-3',
        ]);

        $mock = Mockery::mock(SearchConsoleService::class);
        $mock->shouldReceive('fetchSearchAnalytics')->andReturn([]);
        $this->app->instance(SearchConsoleService::class, $mock);

        Bus::dispatchSync(new SyncSearchConsoleData($website->id));

        $website->refresh();
        $this->assertNotNull($website->last_search_console_sync_at);
    }

    public function test_search_console_sync_does_not_set_timestamp_without_google_account(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $mock = Mockery::mock(SearchConsoleService::class);
        $mock->shouldNotReceive('fetchSearchAnalytics');
        $this->app->instance(SearchConsoleService::class, $mock);

        Bus::dispatchSync(new SyncSearchConsoleData($website->id));

        $website->refresh();
        $this->assertNull($website->last_search_console_sync_at);
    }
}
