<?php

namespace Tests\Feature;

use App\Models\SearchConsoleData;
use App\Models\User;
use App\Models\Website;
use App\Services\PageAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class GscKeywordLookbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fetch_target_keywords_excludes_rows_older_than_website_lookback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-18', 'UTC'));

        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'gsc_keyword_lookback_days' => 30,
        ]);
        $page = 'https://'.$website->domain.'/article';

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => '2026-01-01',
            'query' => 'stale query',
            'page' => $page,
            'clicks' => 100,
            'impressions' => 50_000,
            'position' => 5.0,
            'country' => '',
            'device' => '',
            'ctr' => 0.02,
        ]);

        SearchConsoleData::create([
            'website_id' => $website->id,
            'date' => '2026-04-10',
            'query' => 'fresh query',
            'page' => $page,
            'clicks' => 2,
            'impressions' => 40,
            'position' => 12.0,
            'country' => '',
            'device' => '',
            'ctr' => 0.05,
        ]);

        $svc = $this->app->make(PageAuditService::class);
        $method = new ReflectionMethod(PageAuditService::class, 'fetchTargetKeywords');
        $method->setAccessible(true);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $method->invoke($svc, $website, $page);

        $this->assertCount(1, $rows);
        $this->assertSame('fresh query', $rows[0]['query']);
    }

    public function test_effective_lookback_uses_config_when_column_null(): void
    {
        config(['audit.gsc_keyword_lookback_days_default' => 88]);

        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'gsc_keyword_lookback_days' => null,
        ]);

        $this->assertSame(88, $website->fresh()->effectiveGscKeywordLookbackDays());
    }

    public function test_effective_lookback_clamps_to_max(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'gsc_keyword_lookback_days' => 9999,
        ]);

        $this->assertSame((int) config('audit.gsc_keyword_lookback_days_max', 480), $website->effectiveGscKeywordLookbackDays());
    }
}
