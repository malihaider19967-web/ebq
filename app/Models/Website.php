<?php

namespace App\Models;

use App\Jobs\SyncAnalyticsData;
use App\Jobs\SyncSearchConsoleData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Setting;

class Website extends Model
{
    // Billing has moved to the User model — Cashier's Billable trait
    // now lives on App\Models\User. Tier and freeze state are derived
    // from the owning user's plan (see effectiveTier / isFrozen below).
    use HasApiTokens, HasFactory;

    protected static function booted(): void
    {
        static::created(function (Website $website): void {
            // Skip the 365-day backfill for websites that boot up
            // already over the user's plan limit (would happen in
            // theoretical race conditions during onboarding while the
            // canAddWebsite gate is being added; in steady-state the
            // gate prevents this).
            if ($website->isFrozen()) {
                return;
            }
            SyncAnalyticsData::dispatch($website->id, 365);
            SyncSearchConsoleData::dispatch($website->id, 365);
        });
    }

    /** Subscription tier constants — kept for API-response back-compat */
    public const TIER_FREE = 'free';
    public const TIER_PRO  = 'pro';

    protected $fillable = [
        'user_id',
        'domain',
        'feature_flags',
        'ga_property_id',
        'gsc_site_url',
        'gsc_keyword_lookback_days',
        'report_recipients',
        'last_analytics_sync_at',
        'last_search_console_sync_at',
        'last_traffic_drop_alert_at',
        'last_rank_drop_alert_at',
    ];

    /**
     * Canonical list of toggleable features kept in sync with the
     * WordPress plugin's `EBQ_Feature_Flags::KNOWN_FEATURES`. The admin
     * UI iterates this list to render the toggle grid; the API endpoint
     * merges stored overrides on top of these defaults.
     */
    public const FEATURE_KEYS = [
        'chatbot',
        'ai_writer',
        'ai_inline',
        'live_audit',
        'hq',
        'redirects',
        'dashboard_widget',
        'post_column',
    ];

    /**
     * Per-feature defaults applied when a website hasn't customised the
     * flag explicitly. Heavy AI surfaces (the floating chatbot + the
     * full-post AI Writer) default OFF so customers explicitly enable
     * them via the admin grid; everything else defaults ON because they
     * are core editor enhancements with low risk.
     *
     * Keep this in sync with the marketing default tier presentation —
     * if a customer signs up and the plugin shows nothing they expected,
     * the bug is here.
     *
     * @var array<string, bool>
     */
    public const FEATURE_DEFAULTS = [
        'chatbot'          => false,
        'ai_writer'        => false,
        'ai_inline'        => true,
        'live_audit'       => true,
        'hq'               => true,
        'redirects'        => true,
        'dashboard_widget' => true,
        'post_column'      => true,
    ];

    /**
     * Resolve the effective feature-flag map for this website.
     *
     * Composition (kill-switch precedence):
     *   defaults → per-site overrides → AND'd against global flags
     *
     * If a feature is globally disabled (e.g., emergency kill-switch
     * flipped in `/admin/website-features` global panel), it's hidden
     * regardless of per-site state. A per-site `true` cannot override
     * a global `false`. A per-site `false` always wins (admin can
     * disable features per customer even when global allows them).
     *
     * @return array<string, bool>
     */
    public function effectiveFeatureFlags(): array
    {
        // Resolution order, evaluated from broadest to most specific:
        //   1. Defaults (Website::FEATURE_DEFAULTS) — the canonical
        //      shipped state. chatbot/ai_writer default OFF; rest ON.
        //   2. Global kill-switch (Setting::globalFeatureFlags()) —
        //      ANDed against the defaults. If global says false, the
        //      feature is OFF everywhere regardless of per-site state.
        //   3. Per-website override (`feature_flags` JSON column) —
        //      admin-set in /admin/website-features. Replaces the
        //      effective value when present.
        //   4. Freeze (over plan limit) — when true, every feature is
        //      forced OFF so the WP plugin shows the frozen-state UI.
        $effective = self::FEATURE_DEFAULTS;
        $global = self::globalFeatureFlags();
        foreach ($effective as $key => $value) {
            if (($global[$key] ?? true) === false) {
                $effective[$key] = false;
            }
        }
        $stored = $this->feature_flags;
        if (is_array($stored)) {
            foreach ($stored as $key => $value) {
                if (array_key_exists($key, $effective)) {
                    $effective[$key] = (bool) $value;
                }
            }
        }
        if ($this->isFrozen()) {
            foreach ($effective as $key => $_) {
                $effective[$key] = false;
            }
        }
        return $effective;
    }

    /**
     * Global per-feature kill-switch map, the same shape as
     * FEATURE_DEFAULTS. Persisted in the `settings` table under the
     * `global_feature_flags` key; cached forever in Laravel's cache,
     * invalidated on admin save. Returns FEATURE_DEFAULTS when no
     * row exists yet (fresh database, pre-seeding).
     *
     * Used by both `effectiveFeatureFlags()` (per-site AND'ing) and
     * `WordPressPluginVersionController` (broadcast to unconnected
     * installs via the public version-check endpoint).
     *
     * @return array<string, bool>
     */
    public static function globalFeatureFlags(): array
    {
        $stored = Setting::get('global_feature_flags', null);
        $defaults = self::FEATURE_DEFAULTS;
        if (! is_array($stored)) {
            return $defaults;
        }
        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = (bool) $value;
            }
        }
        return $defaults;
    }

    /**
     * True when this website's tier unlocks paid AI features. Derived
     * from the owning user's plan AND a "not frozen" check, since a
     * website over the user's plan limit drops back to free even if
     * the user themselves is on Pro.
     *
     * Used by every Pro-gating controller. The WP plugin reads `tier`
     * (string) from API responses; effectiveTier() flows through that
     * pathway unchanged.
     */
    public function isPro(): bool
    {
        if ((bool) config('app.free', false)) {
            return true;
        }
        if ($this->isFrozen()) {
            return false;
        }
        $owner = $this->user;
        return $owner !== null && $owner->isPro();
    }

    /**
     * Coarse tier label for API responses (the WP plugin reads this).
     * Frozen sites always report 'free' so plugin features lock out.
     *
     * Honour the global "free for everyone" promo flag (FREE=true in
     * env) — when set, the platform is in promo mode and every site
     * effectively reports `pro` so the plugin's tier-gated UI unlocks.
     * This mirrors `isPro()` which already short-circuits on the same
     * config; without the parallel mirror, the server lets a request
     * through (because `isPro()` is true) but the plugin still shows
     * the "upgrade to Pro" banner because it reads `tier` directly.
     */
    public function effectiveTier(): string
    {
        if ((bool) config('app.free', false)) {
            return self::TIER_PRO;
        }
        if ($this->isFrozen()) {
            return self::TIER_FREE;
        }
        $owner = $this->user;
        return $owner !== null ? $owner->effectiveTier() : self::TIER_FREE;
    }

    /**
     * True when this website is past the owning user's plan limit.
     * Computed live (no stored column) so plan changes take effect on
     * the next read with no migration drift. The user's frozen list
     * is ordered by created_at so the oldest sites stay active.
     */
    public function isFrozen(): bool
    {
        if (! $this->user_id) {
            return false;
        }
        $owner = $this->user;
        if ($owner === null) {
            return false;
        }
        return in_array($this->id, $owner->frozenWebsiteIds(), true);
    }

    protected function casts(): array
    {
        return [
            'report_recipients' => 'array',
            'feature_flags' => 'array',
            'gsc_keyword_lookback_days' => 'integer',
            'last_analytics_sync_at' => 'datetime',
            'last_search_console_sync_at' => 'datetime',
            'last_traffic_drop_alert_at' => 'datetime',
            'last_rank_drop_alert_at' => 'datetime',
        ];
    }

    /**
     * Users who should receive reports. Falls back to the owner if none configured.
     *
     * @return Collection<int, User>
     */
    public function getReportRecipientUsers(): Collection
    {
        $ids = $this->report_recipients;

        if (empty($ids)) {
            return User::where('id', $this->user_id)->get();
        }

        return User::whereIn('id', $ids)->get();
    }

    /**
     * Rolling window (days) for Search Console rows used in page audits and page-level GSC UI.
     */
    public function effectiveGscKeywordLookbackDays(): int
    {
        $default = (int) config('audit.gsc_keyword_lookback_days_default', 28);
        $min = (int) config('audit.gsc_keyword_lookback_days_min', 7);
        $max = (int) config('audit.gsc_keyword_lookback_days_max', 480);
        $raw = $this->gsc_keyword_lookback_days;

        if ($raw === null) {
            return max($min, min($max, $default));
        }

        return max($min, min($max, (int) $raw));
    }

    /**
     * Inclusive lower bound date (Y-m-d) for GSC keyword aggregates: date >= today - N days.
     */
    public function gscKeywordWindowStartDate(?Carbon $today = null): string
    {
        $today ??= Carbon::today((string) config('app.timezone'));

        return $today->copy()->subDays($this->effectiveGscKeywordLookbackDays())->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'website_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WebsiteInvitation::class);
    }

    public function analyticsData(): HasMany
    {
        return $this->hasMany(AnalyticsData::class);
    }

    public function searchConsoleData(): HasMany
    {
        return $this->hasMany(SearchConsoleData::class);
    }

    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class);
    }

    public function pageIndexingStatuses(): HasMany
    {
        return $this->hasMany(PageIndexingStatus::class);
    }

    public function customPageAudits(): HasMany
    {
        return $this->hasMany(CustomPageAudit::class);
    }

    public function pluginInstall(): HasOne
    {
        return $this->hasOne(WebsitePluginInstall::class);
    }

    /**
     * Whether a page URL is on this website's domain or a subdomain of it (www normalized).
     */
    public function isAuditUrlForThisSite(string $url): bool
    {
        $domain = strtolower(trim((string) $this->domain));
        $domain = preg_replace('/^www\./', '', $domain) ?: $domain;
        if ($domain === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
