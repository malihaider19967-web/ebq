<?php

namespace Tests\Feature\Competitive;

use App\Jobs\ReprocessCompetitiveData;
use App\Models\GoogleAccount;
use App\Models\KeywordApiRequest;
use App\Models\KeywordGapAnalysis;
use App\Models\KeywordGapRow;
use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\Competitive\CompetitiveReprocessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompetitiveReprocessTest extends TestCase
{
    use RefreshDatabase;

    private function connectGsc(Website $website): void
    {
        $account = GoogleAccount::factory()->create(['user_id' => $website->user_id]);
        $website->forceFill([
            'gsc_site_url' => 'sc-domain:mysite.com',
            'gsc_google_account_id' => $account->id,
        ])->save();
    }

    public function test_connecting_gsc_dispatches_reprocess_only_on_the_edge(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);

        Queue::fake();
        $this->connectGsc($website);
        Queue::assertPushed(ReprocessCompetitiveData::class, 1);

        // An unrelated save must NOT re-dispatch.
        $website->forceFill(['domain' => 'renamed.com'])->save();
        Queue::assertPushed(ReprocessCompetitiveData::class, 1);
    }

    public function test_reprocess_rebuckets_with_positions_and_no_new_discovery(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $website = Website::factory()->withNoSources()->create(['user_id' => $user->id, 'domain' => 'mysite.com']);

        $analysis = KeywordGapAnalysis::create([
            'website_id' => $website->id, 'user_id' => $user->id, 'our_url' => 'mysite.com',
            'competitor_urls' => ['rival.com'], 'country' => 'us', 'status' => 'completed',
            'expires_at' => now()->addDays(30),
        ]);
        KeywordGapRow::create([
            'keyword_gap_analysis_id' => $analysis->id, 'keyword' => 'shared kw',
            'keyword_hash' => \App\Models\KeywordMetric::hashKeyword('shared kw'),
            'bucket' => 'shared', 'search_volume' => 1000,
        ]);
        KeywordGapRow::create([
            'keyword_gap_analysis_id' => $analysis->id, 'keyword' => 'missing kw',
            'keyword_hash' => \App\Models\KeywordMetric::hashKeyword('missing kw'),
            'bucket' => 'missing', 'search_volume' => 900,
        ]);

        // Connect GSC + provide positions: we rank #4 for "shared", #20 for "missing".
        $this->connectGsc($website);
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => now()->subDay()->toDateString(),
            'query' => 'shared kw', 'page' => 'https://mysite.com/', 'clicks' => 1,
            'impressions' => 100, 'ctr' => 0.01, 'position' => 4.0, 'country' => 'usa', 'device' => 'DESKTOP',
        ]);
        SearchConsoleData::create([
            'website_id' => $website->id, 'date' => now()->subDay()->toDateString(),
            'query' => 'missing kw', 'page' => 'https://mysite.com/', 'clicks' => 1,
            'impressions' => 100, 'ctr' => 0.01, 'position' => 20.0, 'country' => 'usa', 'device' => 'DESKTOP',
        ]);

        $keywordRequestsBefore = KeywordApiRequest::count();
        app(CompetitiveReprocessService::class)->reprocess($website->id);

        // SHARED (we rank #4) → strength; MISSING (now ranking #20) → weak.
        $this->assertSame('strength', KeywordGapRow::where('keyword', 'shared kw')->value('bucket'));
        $this->assertSame('weak', KeywordGapRow::where('keyword', 'missing kw')->value('bucket'));
        $this->assertNotNull($analysis->fresh()->reprocessed_at);

        // The re-diff spends NO keyword-finder budget.
        $this->assertSame($keywordRequestsBefore, KeywordApiRequest::count());
    }
}
