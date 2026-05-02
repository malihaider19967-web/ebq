<?php

namespace App\Http\Middleware;

use App\Models\Website;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inject the current website's effective feature-flag map into every
 * JSON API response that already carries a website context.
 *
 * Why: the WordPress plugin's `EBQ_Feature_Flags::handle_response()`
 * passively syncs flags from any response that includes `features`.
 * Without this middleware, individual controllers would each need to
 * remember to spread the map into their JSON return — and most don't.
 * That meant admin toggles in `/admin/website-features` never reached
 * the plugin until the next explicit `/website-features` fetch (which
 * the plugin never makes outside of `EBQ_Feature_Flags::refresh()`).
 *
 * With this middleware in the auth group, every endpoint the plugin
 * hits (post-insights, seo-score, dashboard, ai-block, …) carries the
 * fresh flag map. The plugin caches it for 12 h client-side; flips on
 * the EBQ admin grid propagate within seconds of any plugin → EBQ
 * round-trip.
 *
 * Safety:
 *   - Only injects when `$request->attributes->get('api_website')` is a
 *     real Website (i.e., `WebsiteApiAuth` ran first and resolved the
 *     bearer token).
 *   - Skips non-JSON responses, file downloads, redirects, etc.
 *   - Does NOT overwrite `features` if the controller already set it
 *     (e.g., the dedicated `/website-features` endpoint).
 *   - Adds a few microseconds per request — negligible vs network RTT.
 */
class InjectFeatureFlags
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only meaningful for JSON responses.
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $website = $request->attributes->get('api_website');
        if (! $website instanceof Website) {
            return $response;
        }

        $data = $response->getData(true);
        if (! is_array($data)) {
            return $response;
        }

        // Don't clobber an explicit features payload from a controller
        // that already builds the map (most notably websiteFeatures).
        $touched = false;
        if (! array_key_exists('features', $data)) {
            $data['features'] = $website->effectiveFeatureFlags();
            $touched = true;
        }
        // Frozen-state signal — the WP plugin reads this to lock its UI
        // (banner, AI-disabled notice, sync skip). `frozen` is derived
        // live from the user's plan limit + ordered websites; freezes
        // and unfreezes propagate on the next API roundtrip with no
        // database writes needed. `tier` is overwritten with the
        // freeze-aware value so the plugin's tier-gated paths see free
        // when the site is over-limit, even if the user's account is on
        // Pro elsewhere.
        if (! array_key_exists('frozen', $data)) {
            $data['frozen'] = $website->isFrozen();
            $touched = true;
        }
        if (! array_key_exists('frozen_reason', $data)) {
            $data['frozen_reason'] = $website->isFrozen() ? 'plan_limit_exceeded' : null;
            $touched = true;
        }
        if (! array_key_exists('tier', $data)) {
            $data['tier'] = $website->effectiveTier();
            $touched = true;
        }
        if ($touched) {
            $response->setData($data);
        }

        return $response;
    }
}
