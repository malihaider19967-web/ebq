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
        'features',
        'display_order',
        'is_active',
        'is_highlighted',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly_usd' => 'integer',
            'price_yearly_usd' => 'integer',
            'trial_days' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'is_highlighted' => 'boolean',
        ];
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
     */
    public function isCheckoutReady(): bool
    {
        return $this->price_monthly_usd > 0
            && ! empty($this->stripe_price_id_monthly);
    }
}
