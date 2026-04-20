<?php

namespace App\Http\Middleware;

use App\Support\TeamPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! array_key_exists($feature, TeamPermissions::FEATURES)) {
            return $next($request);
        }

        $websiteId = (int) session('current_website_id', 0);
        $accessible = $websiteId > 0
            ? $user->accessibleWebsitesQuery()->whereKey($websiteId)->exists()
            : false;
        if (! $accessible) {
            $first = $user->accessibleWebsitesQuery()->select('id')->orderBy('domain')->first();
            $websiteId = $first ? (int) $first->id : 0;
            if ($websiteId > 0) {
                session(['current_website_id' => $websiteId]);
            }
        }

        if ($websiteId > 0 && $user->hasFeatureAccess($feature, $websiteId)) {
            return $next($request);
        }

        $target = $user->firstAccessibleRoute($websiteId);

        if ($request->routeIs($target)) {
            abort(403);
        }

        return redirect()->route($target);
    }
}
