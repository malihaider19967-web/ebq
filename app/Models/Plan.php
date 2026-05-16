<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Subscription plan — one row per public price tier.
 *
 * Drives:
 *   - The marketing /pricing page
 *   - The WordPress plugin's setup-wizard pricing step (via the public
 *     /api/v1/plans endpoint)
 *   - The /billing/checkout endpoint that hands off to Cashier + Stripe
 *
 * Stripe price IDs are nullable so plan rows can exist before their
 * Stripe products are configured (e.g., free tier never has a price ID;
 * during early rollout some paid tiers may not have Stripe products yet).
 *
 * `is_active=false` deprecates a plan without orphaning historical
 * subscription rows that reference it.
 */
class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'tagline',
        'price_monthly_usd',
        'price_yearly_usd',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'trial_days',
        'max_websites',
        'features',
        'research_limits',
        'api_limits',
        'display_order',
        'is_active',
        'is_highlighted',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'research_limits' => 'array',
            'api_limits' => 'array',
            'price_monthly_usd' => 'integer',
            'price_yearly_usd' => 'integer',
            'trial_days' => 'integer',
            'max_websites' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'is_highlighted' => 'boolean',
        ];
    }

    public function researchLimit(string $resource, int $default): int
    {
        $limits = $this->research_limits;
        if (is_array($limits) && isset($limits[$resource]) && is_numeric($limits[$resource])) {
            return (int) $limits[$resource];
        }

        return $default;
    }

    /**
     * Look up a per-provider cap from the `api_limits` JSON via a dot path
     * like "keywords_everywhere.monthly_credits". Returns null if the key
     * is absent or its value is null — callers interpret null as
     * "unlimited" for that provider.
     */
    public function apiLimit(string $path): ?int
    {
        $limits = $this->api_limits;
        if (! is_array($limits)) {
            return null;
        }

        $node = $limits;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        if ($node === null || ! is_numeric($node)) {
            return null;
        }

        return (int) $node;
    }

    /**
     * Human-readable display of the website cap. Null = unlimited.
     */
    public function maxWebsitesLabel(): string
    {
        if ($this->max_websites === null) {
            return 'Unlimited';
        }
        return $this->max_websites === 1 ? '1 website' : ($this->max_websites.' websites');
    }

    /**
     * Scope to active plans, ordered for left-to-right display in
     * pricing cards.
     */
    public function scopeOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    /**
     * True when the plan has a real Stripe price configured and can
     * support a checkout session. Free tier always returns false.
     *
     * EBQ only sells yearly subscriptions — the monthly price column
     * exists purely so we can show "$X/mo, billed yearly" copy on the
     * pricing card. Checkout is always gated on the yearly price ID.
     */
    public function isCheckoutReady(): bool
    {
        return $this->price_yearly_usd > 0
            && ! empty($this->stripe_price_id_yearly);
    }
}
