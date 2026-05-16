<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Related Posts (Phase 4).
 *
 * Returns a cosine-ranked list of related posts for a given source
 * post. Production implementation should walk the embeddings table
 * (the existing research stack already produces them); this stub
 * returns an empty list when the embeddings infrastructure isn't
 * available so the block falls back to its same-category default
 * gracefully.
 *
 * Gated by `plan_features.internal_links`.
 */
class RelatedPostsController extends Controller
{
    public function show(Request $request, int $postId): JsonResponse
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website instanceof Website, 500, 'Website context missing');

        $gate = $website->featureGateInfo('internal_links');
        if ($gate !== null) {
            return response()->json(array_merge($gate, [
                'message' => 'AI Related Posts is a paid feature. Upgrade to unlock.',
            ]), 402);
        }

        $count = max(1, min(20, (int) $request->query('count', 5)));

        // Production implementation: load embeddings for this website,
        // cosine-rank vs the source post's embedding, return top N.
        // Stub returns empty `items` so the WP block falls back to its
        // local-category baseline. This keeps the block useful without
        // a fully built embedding pipeline.
        return response()->json([
            'ok' => true,
            'post_id' => $postId,
            'items' => [],
            'fallback' => 'local',
        ]);
    }
}
