<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Platform-wide page-audit options (admin-configurable).
 */
class AuditConfig
{
    /**
     * When true, completed page audits may queue Keywords Everywhere lookups
     * for competitor domains found in the SERP benchmark (backlink sample).
     */
    public const SETTING_COMPETITOR_KEYWORDS_EVERYWHERE = 'audit.competitor_keywords_everywhere_enabled';

    public static function competitorKeywordsEverywhereEnabled(): bool
    {
        return (bool) Setting::get(
            self::SETTING_COMPETITOR_KEYWORDS_EVERYWHERE,
            (bool) config('audit.competitor_keywords_everywhere_default', false),
        );
    }
}
