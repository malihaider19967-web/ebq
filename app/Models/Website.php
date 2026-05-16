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
     * Composition (highest-priority "off" wins):
     *
     *   freeze            → all-off, short-circuit
     *   plan ceiling      → if the owning user's plan doesn't allow a
     *                       feature, it's OFF regardless of overrides
     *   global kill       → emergency platform-wide kill-switch (AND'd)
     *   per-site override → admin-set per-customer (can only narrow,
     *                       never widen — capped by the plan)
     *   plan defaults     → starting point when no override is set
     *
     * The previous behaviour blended FEATURE_DEFAULTS with overrides;
     * the canonical starting state is now the user's plan entitlement
     * matrix (`User::effectivePlanFeatures()`), with FEATURE_DEFAULTS
     * kept only as a fallback for orphan / userless websites (tests,
     * fixture data, transient onboarding rows before user_id is set).
     *
     * @return array<string, bool>
     */
    public function effectiveFeatureFlags(): array
    {
        // 1. Freeze short-circuits everything — over-plan-limit sites
        //    behave like locked free trials regardless of upstream state.
        if ($this->isFrozen()) {
            return array_fill_keys(self::FEATURE_KEYS, false);
        }

        // 2. Start from the owning user's plan map. Orphan rows fall
        //    back to FEATURE_DEFAULTS so test fixtures / transient
        //    onboarding sites don't 500 just because no plan resolved.
        $owner = $this->user;
        $effective = $owner !== null
            ? $owner->effectivePlanFeatures()
            : self::FEATURE_DEFAULTS;

        // 3. Per-site override — can NARROW (turn off a plan-allowed
        //    flag) but cannot WIDEN (a per-site true on a plan-disallowed
        //    flag is ignored; the plan is the ceiling).
        $stored = $this->feature_flags;
        if (is_array($stored)) {
            foreach ($stored as $key => $value) {
                if (! array_key_exists($key, $effective)) {
                    continue;
                }
                if ((bool) $value === false) {
                    $effective[$key] = false;
                }
                // (bool) $value === true: noop — plan already permits or
                // forbids this; per-site can't override the plan ceiling.
            }
        }

        // 4. Global kill-switch — AND'd last so an emergency disable
        //    propagates regardless of per-plan or per-site state.
        $global = self::globalFeatureFlags();
        foreach ($effective as $key => $value) {
            if (($global[$key] ?? true) === false) {
                $effective[$key] = false;
            }
        }

        return $effective;
    }

    /**
     * Returns the slug of the cheapest plan that would unlock the given
     * feature key for this website's owner. Null when the feature is on
     * already, when no plan enables it, or when the website has no
     * owner. Used by API gating to populate `required_tier` on
     * `tier_required` responses so the plugin can render contextual
     * upgrade copy ("AI Writer is on Startup or above").
     */
    public function featureRequiresUpgrade(string $key): ?string
    {
        $effective = $this->effectiveFeatureFlags();
        if (($effective[$key] ?? false) === true) {
            return null;
        }
        return Plan::requiredPlanFor($key);
    }

    /**
     * Single-call feature gate for the API surface. Returns null when
     * the feature is currently allowed for this website (plan + global
     * + per-site overrides + freeze all permit it), or a structured
     * payload the controller can splat into its 402 JsonResponse.
     *
     * The error code distinguishes two failure modes:
     *
     *   tier_required     — the owner's plan doesn't include the
     *                       feature; the plugin should show an
     *                       "Upgrade to <slug>" CTA. `required_tier`
     *                       points at the cheapest qualifying plan.
     *   feature_disabled  — the plan allows the feature, but either
     *                       the global kill-switch or a per-site
     *                       override turned it off. No amount of
     *                       upgrading fixes this — the workspace admin
     *                       has to flip the switch back.
     *
     * Frozen sites get tier_required too: the user CAN unfreeze by
     * upgrading or removing other sites, so the upgrade CTA is the
     * correct affordance.
     *
     * @return array<string, mixed>|null
     */
    public function featureGateInfo(string $key): ?array
    {
        $effective = $this->effectiveFeatureFlags();
        if (($effective[$key] ?? false) === true) {
            return null;
        }

        $currentTier = $this->effectiveTier();
        $required = Plan::requiredPlanFor($key);
        $order = User::TIER_ORDER;

        // Distinguish "you need a bigger plan" from "your plan supports
        // this but it's been disabled". A required slug that exists AND
        // ranks strictly above the current tier is an upgrade path; the
        // owner's plan already allowing it but the flag still being off
        // means a kill-switch / per-site override is suppressing it.
        $isUpgradePath = $required !== null
            && ($order[$required] ?? 0) > ($order[$currentTier] ?? 0);

        // Frozen sites are coded as tier_required so the plugin renders
        // an Upgrade CTA — unfreezing requires either removing sites or
        // upgrading to a larger plan.
        if ($this->isFrozen()) {
            $isUpgradePath = true;
        }

        return [
            'ok' => false,
            'error' => $isUpgradePath ? 'tier_required' : 'feature_disabled',
            'tier' => $currentTier,
            'required_tier' => $required ?? self::TIER_PRO,
            'feature' => $key,
        ];
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
     * pathway unchanged. After the slug rename, "Pro" is the entry-
     * level paid tier; `isPro()` returns true for any paid tier
     * (pro/startup/agency).
     */
    public function isPro(): bool
    {
        if ($this->isFrozen()) {
            return false;
        }
        $owner = $this->user;
        return $owner !== null && $owner->isPro();
    }

    /**
     * Exact tier slug for API responses (the WP plugin reads this).
     * Frozen sites always report 'free' so plugin features lock out.
     *
     * After the 2026-05-17 rename returns one of: `free`, `pro`,
     * `startup`, `agency`. The free-promo (FREE=true) short-circuit
     * lives in `User::effectivePlan()` — when set, the platform
     * resolves every user to the Pro plan row, and this method then
     * returns `pro` naturally without a parallel check here.
     */
    public function effectiveTier(): string
    {
        if ($this->isFrozen()) {
            return self::TIER_FREE;
        }
        $owner = $this->user;
        return $owner !== null ? $owner->effectiveTier() : self::TIER_FREE;
    }

    /**
     * Ordinal-comparison helper for tier gates: "is this website on at
     * least the Startup tier?". Delegates to the owning user when one
     * is set; orphan websites always return false.
     */
    public function isAtLeast(string $slug): bool
    {
        if ($this->isFrozen()) {
            return $slug === self::TIER_FREE;
        }
        $owner = $this->user;
        return $owner !== null && $owner->isAtLeast($slug);
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
