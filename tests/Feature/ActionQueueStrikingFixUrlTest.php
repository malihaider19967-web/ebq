<?php

namespace Tests\Feature;

use App\Services\ActionQueueService;
use App\Services\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActionQueueStrikingFixUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_striking_distance_fix_points_to_keyword_playbook(): void
    {
        $this->mock(ReportDataService::class, function ($m): void {
            $m->shouldReceive('strikingDistance')->andReturn([[
                'query' => 'blue widgets',
                'page' => 'https://example.test/widgets',
                'page_position' => 11.0,
                'position' => 11.0,
                'impressions' => 3200,
                'clicks' => 40,
                'ctr' => 1.2,
                'upside_value' => 540.0,
                'score' => 30.0,
            ]]);
        });

        $rows = app(ActionQueueService::class)->issueRows('striking_distance', 1, null);

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame('audits', $row['fix_feature']);
        $this->assertStringContainsString('/keywords/fix', $row['fix_url']);
        $this->assertStringContainsString('keyword=blue', $row['fix_url']);
        $this->assertStringContainsString('widgets', $row['fix_url']);
    }
}
