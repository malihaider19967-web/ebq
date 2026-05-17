<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\RankTrackerConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankTrackerConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_check_interval_is_72_hours(): void
    {
        $this->assertSame(72, RankTrackerConfig::checkIntervalHours());
    }

    public function test_admin_setting_overrides_check_interval(): void
    {
        Setting::set(RankTrackerConfig::SETTING_CHECK_INTERVAL, 48);

        $this->assertSame(48, RankTrackerConfig::checkIntervalHours());
    }

    public function test_normalize_target_url_from_path(): void
    {
        $url = RankTrackerConfig::normalizeTargetUrl('example.com', '/blog/post');

        $this->assertSame('https://example.com/blog/post', $url);
    }

    public function test_rejects_url_on_wrong_domain(): void
    {
        $this->assertNull(
            RankTrackerConfig::normalizeTargetUrl('example.com', 'https://other.com/page')
        );
    }
}
