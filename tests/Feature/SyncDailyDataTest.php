<?php

namespace Tests\Feature;

use App\Jobs\SyncAnalyticsData;
use App\Jobs\SyncSearchConsoleData;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncDailyDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_sync_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Website::factory()->create(['user_id' => $user->id]);

        $this->artisan('growthhub:sync-daily-data')->assertSuccessful();

        Queue::assertPushed(SyncAnalyticsData::class);
        Queue::assertPushed(SyncSearchConsoleData::class);
    }
}
