<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        $websites = Website::query()
            ->select(['id', 'domain', 'tier', 'feature_flags', 'user_id', 'created_at'])
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
        ]);
    }

    /**
     * Update the feature-flag map on a single website. Posts as a form
     * with `feature_flags[<key>] = "1"` (checkbox checked). Unchecked
     * boxes don't submit, so absence of a key in $_POST means OFF.
     *
     * Stored shape: `{ <key>: bool, ... }`. Only keys in FEATURE_KEYS
     * land in the column — keeps JSON predictable. Storing TRUE is
     * implicit (same as default), so we save space by only persisting
     * explicit FALSE values. The WP plugin's `effectiveFeatureFlags()`
     * + `store()` both treat absent keys as enabled.
     */
    public function update(Request $request, Website $website): RedirectResponse
    {
        $posted = (array) $request->input('feature_flags', []);
        $clean = [];
        foreach (Website::FEATURE_KEYS as $key) {
            // Checkbox unchecked → the key is missing from $posted →
            // the feature is OFF for this website. Checked → present
            // → ON; we don't store the explicit TRUE since absence
            // already means default-ON downstream.
            if (! array_key_exists($key, $posted)) {
                $clean[$key] = false;
            }
        }

        $website->feature_flags = empty($clean) ? null : $clean;
        $website->save();

        return redirect()
            ->route('admin.website-features.index', $request->query())
            ->with('status', sprintf('Feature flags saved for %s.', $website->domain));
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
