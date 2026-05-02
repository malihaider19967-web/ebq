<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\TeamPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /** Subscription tier constants — mirrors the legacy Website::TIER_* */
    public const TIER_FREE = 'free';
    public const TIER_PRO = 'pro';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'timezone',
        'is_admin',
        'is_disabled',
        'password',
        // Cashier billing columns + plan snapshot. Cashier reads these
        // off the billable model; current_plan_slug is our snapshot of
        // the active subscription's plan slug for fast read-path checks
        // (website limits, frozen-site decisions).
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'current_plan_slug',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_growth_report_sent_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_disabled' => 'boolean',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function googleAccounts(): HasMany
    {
        return $this->hasMany(GoogleAccount::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function customPageAudits(): HasMany
    {
        return $this->hasMany(CustomPageAudit::class);
    }

    public function sharedWebsites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'website_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Role for this user on the given website ('owner', 'admin', 'member') or null.
     */
    public function roleForWebsite(int $websiteId): ?string
    {
        if ($websiteId <= 0) {
            return null;
        }

        $ownerCount = Website::query()->whereKey($websiteId)->where('user_id', $this->id)->count();
        if ($ownerCount > 0) {
            return TeamPermissions::ROLE_OWNER;
        }

        $row = DB::table('website_user')
            ->where('website_id', $websiteId)
            ->where('user_id', $this->id)
            ->first();

        if (! $row) {
            return null;
        }

        return (string) ($row->role ?: TeamPermissions::ROLE_MEMBER);
    }

    /**
     * @return list<string>|null
     */
    public function permissionsForWebsite(int $websiteId): ?array
    {
        $role = $this->roleForWebsite($websiteId);
        if ($role === null) {
            return null;
        }
        if ($role === TeamPermissions::ROLE_OWNER || $role === TeamPermissions::ROLE_ADMIN) {
            return null;
        }

        $row = DB::table('website_user')
            ->where('website_id', $websiteId)
            ->where('user_id', $this->id)
            ->first();

        if (! $row || $row->permissions === null) {
            return null;
        }

        $decoded = json_decode((string) $row->permissions, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : null;
    }

    public function hasFeatureAccess(string $feature, int $websiteId): bool
    {
        $role = $this->roleForWebsite($websiteId);
        if ($role === null) {
            return false;
        }

        return TeamPermissions::allows($role, $this->permissionsForWebsite($websiteId), $feature);
    }

    /**
     * Route name of the first feature this user can access on the given website.
     * Falls back to websites.index (accessible to anyone with ≥1 website).
     */
    public function firstAccessibleRoute(int $websiteId): string
    {
        if ($websiteId > 0) {
            foreach (TeamPermissions::FEATURES as $key => $meta) {
                if ($this->hasFeatureAccess($key, $websiteId)) {
                    return $meta['route'];
                }
            }
        }

        return 'websites.index';
    }

    public function canManageTeamFor(int $websiteId): bool
    {
        $role = $this->roleForWebsite($websiteId);

        return $role === TeamPermissions::ROLE_OWNER || $role === TeamPermissions::ROLE_ADMIN;
    }

    public function timezoneForDisplay(): string
    {
        $tz = $this->timezone;
        if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return (string) config('app.timezone');
    }

    /**
     * Websites this user owns or has been granted access to.
     *
     * @return Builder<Website>
     */
    public function accessibleWebsitesQuery(): Builder
    {
        return Website::query()
            ->where(function (Builder $q): void {
                $q->where('websites.user_id', $this->id)
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('website_user')
                            ->whereColumn('website_user.website_id', 'websites.id')
                            ->where('website_user.user_id', $this->id);
                    });
            });
    }

    public function hasAccessibleWebsites(): bool
    {
        return $this->accessibleWebsitesQuery()->exists();
    }

    public function canViewWebsiteId(int $websiteId): bool
    {
        if ($websiteId <= 0) {
            return false;
        }

        $website = Website::find($websiteId);

        return $website !== null && $this->can('view', $website);
    }

    /* ─── Billing & plan helpers ────────────────────────────────────
     * Per-user billing: one Cashier subscription per user, the active
     * plan caps how many websites the user can manage. Below are the
     * read-paths every consumer (controllers, middleware, views, jobs)
     * uses to ask "what plan? how many sites? which sites are frozen?"
     */

    /**
     * The Plan row the user is currently on, derived from the active
     * Cashier subscription's stripe_price_id_yearly. Falls back to the
     * snapshot in `current_plan_slug` if Stripe is unreachable. Returns
     * null for free-tier users (no active subscription).
     */
    public function effectivePlan(): ?Plan
    {
        $subscription = $this->subscription('default');
        if ($subscription && $subscription->valid()) {
            $price = (string) $subscription->stripe_price;
            if ($price !== '') {
                $plan = Plan::where('stripe_price_id_yearly', $price)->first();
                if ($plan) {
                    return $plan;
                }
            }
        }
        if (! empty($this->current_plan_slug)) {
            return Plan::where('slug', $this->current_plan_slug)->first();
        }
        return null;
    }

    /**
     * Coarse tier flag for feature gating. A user is `pro` whenever
     * they have an active Cashier subscription (active OR trialing OR
     * cancelled-but-in-grace), `free` otherwise.
     */
    public function effectiveTier(): string
    {
        return $this->subscribed('default') ? self::TIER_PRO : self::TIER_FREE;
    }

    /**
     * Convenience for the most common gate.
     */
    public function isPro(): bool
    {
        return $this->effectiveTier() === self::TIER_PRO;
    }

    /**
     * Maximum websites the user's current plan allows. Null = unlimited
     * (the Agency tier). Free-tier users (no plan) always get 1.
     */
    public function websiteLimit(): ?int
    {
        $plan = $this->effectivePlan();
        if ($plan === null) {
            return 1;
        }
        return $plan->max_websites;
    }

    /**
     * IDs of websites past the user's current limit, ordered by
     * created_at — i.e. the oldest sites stay active, newer ones are
     * frozen on a downgrade. Computed live (no stored column) so plan
     * changes take effect on the next read with no migration drift.
     *
     * @return list<int>
     */
    public function frozenWebsiteIds(): array
    {
        $limit = $this->websiteLimit();
        if ($limit === null) {
            return [];
        }
        $owned = Website::where('user_id', $this->id)
            ->orderBy('created_at')
            ->pluck('id')
            ->all();
        if (count($owned) <= $limit) {
            return [];
        }
        return array_slice($owned, $limit);
    }

    /**
     * True when the user can add another website without breaking
     * their plan limit. Onboarding + the admin "add website" flow gate
     * on this; the UI can also use it to render a disabled CTA.
     */
    public function canAddWebsite(): bool
    {
        $limit = $this->websiteLimit();
        if ($limit === null) {
            return true;
        }
        return Website::where('user_id', $this->id)->count() < $limit;
    }
}
