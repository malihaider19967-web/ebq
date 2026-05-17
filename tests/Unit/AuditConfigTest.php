<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\AuditConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_competitor_keywords_everywhere_disabled_by_default(): void
    {
        $this->assertFalse(AuditConfig::competitorKeywordsEverywhereEnabled());
    }

    public function test_competitor_keywords_everywhere_reads_admin_setting(): void
    {
        Setting::set(AuditConfig::SETTING_COMPETITOR_KEYWORDS_EVERYWHERE, true);

        $this->assertTrue(AuditConfig::competitorKeywordsEverywhereEnabled());
    }
}
