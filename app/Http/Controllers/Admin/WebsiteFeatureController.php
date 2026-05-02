<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin grid for per-website feature flags. Each row is a connected
 * website; each column is one of `Website::FEATURE_KEYS`. Toggling a
 * cell flips the boolean in `websites.feature_flags` JSON; the WP
 * plugin picks up the change on its next API call (passive sync via
 * `EBQ_Feature_Flags::store()` reading the `features` field on every
 * response) — and at the latest within 12 hours when the cached
 * transient on the WP side expires.
 *
 * Layout follows the existing `Admin\ClientController` pattern: a
 * single index() that returns a Blade view + an update() that handles
 * the toggle POST and redirects back. No Livewire — keeps the page
 * fast and the shape predictable for an admin-only surface.
 */
class WebsiteFeatureController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        // The per-user-billing migration dropped `tier` and
        // `feature_flags` columns from `websites`. Tier is now derived
        // (Website::effectiveTier()); per-website feature overrides
        // were retired in favour of the single global-flags map. The
        // view consequently reads tier through the method and renders
        // the global-flags map (read-only per row) instead of a
        // per-site override toggle.
        $websites = Website::query()
            ->select(['id', 'domain', 'user_id', 'created_at'])
            ->with(['owner:id,name,email'])
            ->when($q !== '', static function ($qq) use ($q) {
                $qq->where('domain', 'like', '%'.$q.'%');
            })
            ->orderBy('domain')
            ->paginate(50)
            ->withQueryString();

        return view('admin.website-features.index', [
            'websites'      => $websites,
            'q'             => $q,
            'feature_keys'  => Website::FEATURE_KEYS,
            'feature_labels' => self::featureLabels(),
            'global_flags'  => Website::globalFeatureFlags(),
        ]);
    }

    /**
     * Update the master global kill-switch map. Stored in the `settings`
     * table under key `global_feature_flags`. Read by both the per-site
     * `effectiveFeatureFlags()` AND'ing logic (which rejects a per-site
     * `true` when global says false) and the public version-check
     * endpoint that broadcasts to unconnected installs.
     */
    public function globalUpdate(Request $request): RedirectResponse
    {
        $posted = (array) $request->input('global_features', []);
        $clean = [];
        foreach (Website::FEATURE_KEYS as $key) {
            $clean[$key] = array_key_exists($key, $posted);
        }

        Setting::set('global_feature_flags', $clean);

        return redirect()
            ->route('admin.website-features.index')
            ->with('status', 'Global feature flags saved. Connected sites pick up the change on the next API call (~seconds). Unconnected sites pick it up on the next plugin update check (~6 h).');
    }

    /**
     * Update the feature-flag map on a single website. Posts as a form
     * with `feature_flags[<key>] = "1"` (checkbox checked). Unchecked
     * boxes don't submit so absence of a key in $_POST means OFF.
     *
     * Stored shape: `{ <key>: bool, ... }` — the FULL 8-key map. We
     * store both TRUE and FALSE explicitly because per-key defaults
     * differ (chatbot/ai_writer default-off, others default-on); if we
     * stored only FALSE values, toggling chatbot ON in the grid would
     * leave the column empty and `effectiveFeatureFlags` would resolve
     * back to the default-OFF — i.e., the user's "ON" intent would
     * silently vanish on the next save.
     *
     * Only keys in FEATURE_KEYS land in the column — junk POSTed
     * keys can't poison the JSON.
     */
    /**
     * Per-website feature override path. Retired during the per-user-
     * billing migration that dropped the `feature_flags` column from
     * `websites`. The route is kept (legacy form posts) but the
     * handler no-ops with a notice — admins should use the global
     * toggle row at the top of the grid instead.
     *
     * If a future workstream restores per-site overrides, this is the
     * single place to wire it back up.
     */
    public function update(Request $request, Website $website): RedirectResponse
    {
        return redirect()
            ->route('admin.website-features.index', $request->query())
            ->with('status', 'Per-website feature overrides have been retired. Use the global toggle row at the top of this page to enable / disable features for every site at once.');
    }

    /**
     * @return array<string, string>
     */
    private static function featureLabels(): array
    {
        return [
            'chatbot'          => 'Rank Assist chatbot',
            'ai_writer'        => 'AI Writer page',
            'ai_inline'        => 'AI inline block toolbar',
            'live_audit'       => 'Live SEO score / audit',
            'hq'               => 'EBQ HQ (rank tracking)',
            'redirects'        => 'Redirects + 404 tracker',
            'dashboard_widget' => 'Dashboard widget',
            'post_column'      => 'Posts-list EBQ column',
        ];
    }
}
