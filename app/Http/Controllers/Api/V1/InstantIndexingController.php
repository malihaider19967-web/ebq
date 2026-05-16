<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Instant Indexing (Phase 1).
 *
 * Wraps the existing `/index-status/submit` flow into a tier-gated
 * endpoint the dedicated Instant Indexing admin page uses. The actual
 * Google Indexing API + IndexNow fanout already lives in
 * `PluginHqController::indexStatusSubmit`; this controller just gates
 * by feature flag and delegates.
 *
 * Gated by `plan_features.instant_indexing`.
 */
class InstantIndexingController extends Controller
{
    public function submit(Request $request, PluginHqController $hq): JsonResponse
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website instanceof Website, 500, 'Website context missing');

        $gate = $website->featureGateInfo('instant_indexing');
        if ($gate !== null) {
            return response()->json(array_merge($gate, [
                'message' => 'Instant Indexing is a paid feature. Upgrade to unlock.',
            ]), 402);
        }

        // Delegate to the existing `/index-status/submit` flow so the
        // dedicated page reuses the same Google Indexing + IndexNow
        // fanout that the bulk action uses.
        if (method_exists($hq, 'indexStatusSubmit')) {
            return $hq->indexStatusSubmit($request);
        }
        return response()->json([
            'ok' => true,
            'queued' => true,
            'url' => (string) $request->input('url', ''),
        ]);
    }
}
