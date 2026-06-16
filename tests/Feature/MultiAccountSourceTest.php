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

class MultiAccountSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_each_sync_job_uses_its_own_per_source_account(): void
    {
        $user = User::factory()->create();
        // GA lives on account A; GSC lives on a different login, account B.
        $accountA = GoogleAccount::factory()->create(['user_id' => $user->id, 'google_id' => 'ga-acct', 'email' => 'ga@x.com']);
        $accountB = GoogleAccount::factory()->create(['user_id' => $user->id, 'google_id' => 'gsc-acct', 'email' => 'gsc@x.com']);

        // Bind mocks BEFORE creating the website so the Website::created
        // hook (which dispatches the sync jobs synchronously in tests)
        // records through them instead of hitting the real Google client.
        $gaAccountUsed = null;
        $ga = Mockery::mock(GoogleAnalyticsService::class);
        $ga->shouldReceive('fetchDailyTraffic')
            ->andReturnUsing(function (GoogleAccount $account) use (&$gaAccountUsed) {
                $gaAccountUsed = $account->id;
                return [];
            });
        $this->app->instance(GoogleAnalyticsService::class, $ga);

        $gscAccountUsed = null;
        $sc = Mockery::mock(SearchConsoleService::class);
        $sc->shouldReceive('fetchSearchAnalytics')
            ->andReturnUsing(function (GoogleAccount $account) use (&$gscAccountUsed) {
                $gscAccountUsed = $account->id;
                return [];
            });
        $this->app->instance(SearchConsoleService::class, $sc);

        $website = Website::factory()->create([
            'user_id' => $user->id,
            'ga_property_id' => 'properties/123',
            'ga_google_account_id' => $accountA->id,
            'gsc_site_url' => 'sc-domain:example.com',
            'gsc_google_account_id' => $accountB->id,
        ]);

        // Drive explicitly too, to be unambiguous about which job ran.
        Bus::dispatchSync(new SyncAnalyticsData($website->id));
        Bus::dispatchSync(new SyncSearchConsoleData($website->id));

        // Each job resolved its OWN per-source account, not just latest().
        $this->assertSame($accountA->id, $gaAccountUsed, 'GA sync should use the GA-source account A');
        $this->assertSame($accountB->id, $gscAccountUsed, 'GSC sync should use the GSC-source account B');
    }
}
