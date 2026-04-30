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
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Setting;

class Website extends Model
{
    // Billable mixes in Cashier's Stripe helpers (newSubscription, subscribed,
    // onTrial, subscription, etc.). Tier is per-website so the model is the
    // billable target — a single user with multiple sites can run distinct
    // subscriptions per site. Cashier reads from the stripe_id, pm_type,
    // pm_last_four, trial_ends_at columns added by the migration.
    use Billable, HasApiTokens, HasFactory;

    protected static function booted(): void
    {
        static::created(function (Website $website): void {
            // Any newly created website gets an immediate 365-day backfill
            // so dashboards are populated without manual sync commands.
            SyncAnalyticsData::dispatch($website->id, 365);
            SyncSearchConsoleData::dispatch($website->id, 365);
        });
    }

    /** Subscription tier constants — drives gating for AI features. */
    public const TIER_FREE = 'free';
    public const TIER_PRO  = 'pro';

    protected $fillable = [
        'user_id',
        'domain',
        'tier',
        'feature_flags',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
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
        $effective = self::FEATURE_DEFAULTS;
        $stored = $this->feature_flags;
        if (is_array($stored)) {
            foreach ($stored as $key => $value) {
                if (array_key_exists($key, $effective)) {
                    $effective[$key] = (bool) $value;
                }
            }
        }
        // AND with global kill-switch — global FALSE always wins.
        $global = self::globalFeatureFlags();
        foreach ($effective as $key => $value) {
            if (($global[$key] ?? true) === false) {
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
     * True when this website's tier unlocks paid AI features (snippet
     * rewrites, content briefs, redirect matching). Single check used
     * everywhere — controllers gate on this, plugin reads `tier` to
     * decide whether to render the action button or a Pro CTA.
     */
    public function isPro(): bool
    {
        return (bool) config('app.free', false) || $this->tier === self::TIER_PRO;
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
            'trial_ends_at' => 'datetime',
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
