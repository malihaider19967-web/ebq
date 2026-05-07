<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-4 staged-rollout gate. Chained alongside `feature:research` so a
 * user's plan-level feature access is only sufficient when the current
 * website is in the rollout allowlist (during admin / cohort phases).
 *
 *   mode=ga     → no-op, all owners with feature:research are admitted.
 *   mode=cohort → website_id must be in research.rollout.allowlist.
 *   mode=admin  → website_id must be in research.rollout.allowlist
 *                 (typically a single internal site during the soak).
 *
 * Failure mode: redirect to dashboard rather than 403, so the sidebar
 * "Research" link doesn't dead-end teammates added before they're
 * eligible — they land on a screen they can use instead.
 */
class EnsureResearchRolloutAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = \App\Support\ResearchEngineSettings::rolloutMode();
        if ($mode === 'ga') {
            return $next($request);
        }

        $websiteId = (int) session('current_website_id', 0);
        $allowlist = \App\Support\ResearchEngineSettings::rolloutAllowlist();

        if ($websiteId > 0 && in_array($websiteId, $allowlist, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error' => 'research_rollout_pending',
                'message' => 'Research is in '.$mode.' rollout. This website is not in the allowlist yet.',
            ], 403);
        }

        return redirect()->route('dashboard')
            ->with('research_rollout_pending', true);
    }
}
