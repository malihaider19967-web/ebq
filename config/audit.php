<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search Console keyword window (page audits)
    |--------------------------------------------------------------------------
    |
    | Used when websites.gsc_keyword_lookback_days is null. Each website can
    | override this in Settings → Reports. Values are clamped to min/max.
    |
    */
    'gsc_keyword_lookback_days_default' => 28,

    'gsc_keyword_lookback_days_min' => 7,

    'gsc_keyword_lookback_days_max' => 480,

    /*
    |--------------------------------------------------------------------------
    | Competitor Keywords Everywhere (audit-triggered)
    |--------------------------------------------------------------------------
    |
    | Default when the admin setting is unset. Audits do not call Keywords
    | Everywhere for competitor SERP domains unless enabled in Admin → Page audits.
    |
    */
    'competitor_keywords_everywhere_default' => false,

];
