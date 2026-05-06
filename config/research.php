<?php

/**
 * Phase-4 research-section runtime config. Quotas live under
 * `services.research.limits` (existing convention); this file holds the
 * staged-rollout gate + the embeddings flag so they can be flipped
 * without code changes.
 */

return [
    'rollout' => [
        // mode = 'admin' | 'cohort' | 'ga'
        //   admin   only website IDs in `allowlist` can hit /research/*
        //   cohort  same as admin but the allowlist is typically larger
        //   ga      no allowlist enforcement (default after rollout completes)
        'mode' => env('RESEARCH_ROLLOUT_MODE', 'ga'),
        'allowlist' => array_filter(array_map(
            'intval',
            explode(',', (string) env('RESEARCH_ROLLOUT_ALLOWLIST', ''))
        )),
    ],

    'embeddings' => [
        'enabled' => filter_var(env('RESEARCH_EMBEDDINGS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
