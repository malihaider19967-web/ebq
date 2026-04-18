<?php

namespace Tests\Unit;

use App\Models\CustomPageAudit;
use App\Models\PageAuditReport;
use PHPUnit\Framework\TestCase;

class CustomPageAuditSerpSampleTest extends TestCase
{
    public function test_serp_sample_gl_prefers_user_chosen(): void
    {
        $report = new PageAuditReport;
        $report->result = [
            'page_locale' => [
                'hl' => 'en',
                'gl' => null,
                'serp_gl_user_chosen' => 'ca',
                'serp_gl_effective' => 'ca',
            ],
        ];

        $this->assertSame('ca', CustomPageAudit::serpSampleGlFromReportResult($report));
    }
}
