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

];
