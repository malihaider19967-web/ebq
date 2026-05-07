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

    // Auto-fetch search volume / CPC / competition from KeywordsEverywhere
    // during pipeline enrichment. Off by default — KE charges 1 credit
    // per keyword per request, so flip it on only when the operator wants
    // to spend budget on broad enrichment. Manual one-off lookups (e.g.
    // from /research/keywords) still hit KE regardless.
    'auto_fetch_volume' => filter_var(env('RESEARCH_AUTO_FETCH_VOLUME', false), FILTER_VALIDATE_BOOLEAN),

    // Competitor scraper subprocess. The Python tool is invoked by
    // RunCompetitorScanJob; it reads MySQL credentials from this same
    // Laravel .env, so no credential duplication.
    //
    // Default python_path resolution order:
    //   1. RESEARCH_SCRAPER_PYTHON env var if set
    //   2. tools/competitor-scraper/.venv/bin/python (Linux/macOS venv)
    //   3. tools/competitor-scraper/.venv/Scripts/python.exe (Windows venv)
    //   4. `python3` on Linux/macOS, `python` on Windows
    //
    // Modern Ubuntu only ships `python3`, so the bare `python` default
    // is wrong there. Auto-detect ensures a freshly-cloned host doesn't
    // 127-exit on the first job.
    'scraper' => [
        'python_path' => env('RESEARCH_SCRAPER_PYTHON', (function () {
            $venvPosix = base_path('tools/competitor-scraper/.venv/bin/python');
            $venvWin = base_path('tools/competitor-scraper/.venv/Scripts/python.exe');
            if (is_file($venvPosix)) {
                return $venvPosix;
            }
            if (is_file($venvWin)) {
                return $venvWin;
            }

            return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        })()),
        'cwd' => env('RESEARCH_SCRAPER_CWD', base_path('tools/competitor-scraper')),
        'timeout_seconds' => (int) env('RESEARCH_SCRAPER_TIMEOUT', 3600),
        'ceiling_total_pages' => (int) env('RESEARCH_SCRAPER_CEILING_PAGES', 5000),
        'ceiling_external_per_domain' => (int) env('RESEARCH_SCRAPER_CEILING_EXT_DOMAIN', 25),
        'ceiling_depth' => (int) env('RESEARCH_SCRAPER_CEILING_DEPTH', 6),
    ],
];
