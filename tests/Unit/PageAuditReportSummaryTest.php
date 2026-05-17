<?php

namespace Tests\Unit;

use App\Models\PageAuditReport;
use App\Support\Audit\PageAuditReportSummary;
use App\Support\Audit\RecommendationEngine;
use Tests\TestCase;

class PageAuditReportSummaryTest extends TestCase
{
    public function test_summary_computes_score_and_top_issue(): void
    {
        $report = new PageAuditReport([
            'status' => 'completed',
            'http_status' => 200,
            'response_time_ms' => 240,
            'result' => [
                'content' => ['word_count' => 1500],
                'keywords' => [
                    'available' => true,
                    'coverage' => ['score' => 72.5],
                ],
                'recommendations' => [
                    ['severity' => RecommendationEngine::SEV_CRITICAL, 'title' => 'Missing title tag'],
                    ['severity' => RecommendationEngine::SEV_WARNING, 'title' => 'Meta description short'],
                ],
            ],
        ]);

        $summary = PageAuditReportSummary::fromReport($report);

        $this->assertNotNull($summary);
        $this->assertSame(79, $summary['score']);
        $this->assertSame('warn', $summary['score_tone']);
        $this->assertSame('Needs attention', $summary['score_label']);
        $this->assertSame(1, $summary['issues']['critical']);
        $this->assertSame(1, $summary['issues']['warning']);
        $this->assertSame('Missing title tag', $summary['top_issue']);
        $this->assertSame(1500, $summary['word_count']);
        $this->assertSame(240, $summary['response_time_ms']);
        $this->assertSame(72.5, $summary['keyword_coverage_pct']);
    }

    public function test_summary_returns_null_for_failed_report(): void
    {
        $report = new PageAuditReport(['status' => 'failed', 'result' => null]);

        $this->assertNull(PageAuditReportSummary::fromReport($report));
    }
}
