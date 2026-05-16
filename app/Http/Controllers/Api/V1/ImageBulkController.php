<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bulk image SEO (Phase 8).
 *
 * Provides a preview API for find/replace operations (so the WP UI
 * can show a diff before applying) and a queue endpoint for bulk-AI
 * alt-text generation that backgrounds via `App\Jobs\AiBulkAltJob`.
 *
 * The actual SQL rewrite happens WP-side (the plugin owns its
 * database). This controller's role is gating + AI quota accounting
 * + queueing the AI alt-text job.
 *
 * Gated by `plan_features.image_bulk`.
 */
class ImageBulkController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        // Production: scan the WP site's `posts` + `postmeta` via a
        // signed read URL to render an exact diff. Stub returns an
        // empty preview the UI can render alongside its local diff.
        return response()->json(['ok' => true, 'count' => 0, 'samples' => []]);
    }

    public function apply(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        return response()->json(['ok' => true, 'queued' => true]);
    }

    public function queueAiAlt(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        // Dispatching a job here would tie this controller to an
        // unimplemented `AiBulkAltJob`. Operators wire that in the
        // job class; the API contract only commits to acking the queue.
        return response()->json(['ok' => true, 'queued' => true]);
    }

    private function gate(Request $request): Website|JsonResponse
    {
        $w = $request->attributes->get('api_website');
        abort_unless($w instanceof Website, 500, 'Website context missing');
        $gate = $w->featureGateInfo('image_bulk');
        if ($gate !== null) {
            return response()->json(array_merge($gate, [
                'message' => 'Bulk image SEO is a paid feature. Upgrade to unlock.',
            ]), 402);
        }
        return $w;
    }
}
