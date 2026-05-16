<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics Pro tabs (Phase 9):
 *   - AI traffic split (Google, Perplexity, ChatGPT, Discover, …)
 *   - Algorithm-update overlay (Google core updates with dates)
 *   - Top winners / losers (by query and by URL)
 *   - Page-level drilldown
 *
 * Gated by `plan_features.analytics_pro`. Heavy lifting can live in
 * dedicated services; this controller returns sensible defaults so
 * the WP UI renders something usable on day 1.
 */
class AnalyticsProController extends Controller
{
    public function aiTrafficSplit(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        $window = max(7, min(180, (int) $request->query('days', 30)));

        // Stub split: real implementation classifies referrer/user-agent
        // patterns over the analytics_data rows.
        return response()->json([
            'ok' => true,
            'window_days' => $window,
            'sources' => [
                ['name' => 'Google Search',     'sessions' => 0, 'share' => 0],
                ['name' => 'Google Discover',   'sessions' => 0, 'share' => 0],
                ['name' => 'Perplexity',        'sessions' => 0, 'share' => 0],
                ['name' => 'ChatGPT-User',      'sessions' => 0, 'share' => 0],
                ['name' => 'OAI-SearchBot',     'sessions' => 0, 'share' => 0],
                ['name' => 'Direct / Other',    'sessions' => 0, 'share' => 0],
            ],
        ]);
    }

    public function algorithmUpdates(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        // Curated Google update registry. Operators extend the array
        // as new updates land; the WP chart overlays them as vertical
        // lines on the SEO Performance graph.
        return response()->json([
            'ok' => true,
            'updates' => [
                ['date' => '2025-11-19', 'name' => 'November 2025 core update',  'url' => 'https://developers.google.com/search/blog/2025/11/november-2025-core-update'],
                ['date' => '2025-08-15', 'name' => 'August 2025 spam update',    'url' => 'https://developers.google.com/search/blog/2025/08/august-2025-spam-update'],
                ['date' => '2025-06-30', 'name' => 'June 2025 core update',      'url' => 'https://developers.google.com/search/blog/2025/06/june-2025-core-update'],
                ['date' => '2025-03-05', 'name' => 'March 2025 core update',     'url' => 'https://developers.google.com/search/blog/2025/03/march-2025-core-update'],
                ['date' => '2024-08-15', 'name' => 'August 2024 core update',    'url' => 'https://developers.google.com/search/blog/2024/08/august-2024-core-update'],
            ],
        ]);
    }

    public function winnersLosers(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        $window = max(7, min(90, (int) $request->query('days', 28)));

        // Stub — operators implement WinnersLosersService that diffs
        // GSC click totals between two windows. Returning empty arrays
        // gives the WP UI a friendly "no data yet" state.
        return response()->json([
            'ok' => true,
            'window_days' => $window,
            'winners_queries' => [],
            'losers_queries'  => [],
            'winners_pages'   => [],
            'losers_pages'    => [],
        ]);
    }

    public function pageDrilldown(Request $request, int $postId): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        // Returns the same shape the HQ chart's `performance` endpoint
        // produces — for a single page. Real implementation joins
        // `analytics_data` + `search_console_data` filtered by URL.
        return response()->json([
            'ok' => true,
            'post_id' => $postId,
            'series' => [],
            'totals' => [
                'sessions' => 0,
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0,
                'avg_position' => null,
            ],
            'top_queries' => [],
        ]);
    }

    private function gate(Request $request): Website|JsonResponse
    {
        $w = $request->attributes->get('api_website');
        abort_unless($w instanceof Website, 500, 'Website context missing');
        $gate = $w->featureGateInfo('analytics_pro');
        if ($gate !== null) {
            return response()->json(array_merge($gate, [
                'message' => 'Analytics Pro is a paid feature. Upgrade to unlock.',
            ]), 402);
        }
        return $w;
    }
}
