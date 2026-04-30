<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe webhook handler — extends Cashier's so we keep all the standard
 * subscription bookkeeping (cashier_subscriptions table, subscription
 * items, trial state) and just override the events we care about for
 * tier sync.
 *
 * Tier reflects billing reality:
 *   - Subscription `active` or `trialing` (any subscription on the
 *     billable Website) → tier = 'pro'
 *   - Subscription `canceled` / `incomplete_expired` / `unpaid` /
 *     `past_due` (after Stripe's grace period) → tier = 'free'
 *
 * The plugin reads `tier` from every authenticated API response (auto
 * sync via `EBQ_Api_Client::handle_response`), so a webhook firing here
 * means the customer's WordPress editor flips into / out of Pro within
 * seconds of the Stripe state change — no manual refresh required.
 */
class StripeWebhookController extends CashierController
{
    /**
     * Handle subscription created / updated. Cashier's parent method
     * does the table bookkeeping; we run after to sync `tier` on the
     * affected Website.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);
        $this->syncTierFromStripeCustomer($payload['data']['object']['customer'] ?? null);
        return $response;
    }

    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);
        $this->syncTierFromStripeCustomer($payload['data']['object']['customer'] ?? null);
        return $response;
    }

    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);
        $this->syncTierFromStripeCustomer($payload['data']['object']['customer'] ?? null);
        return $response;
    }

    /**
     * Look up the Website by Stripe customer ID and recompute its tier
     * by walking its current subscriptions. Cashier's `subscribed()`
     * helper already considers `active` + `trialing` as "yes", which is
     * exactly the boundary we want for Pro access.
     */
    private function syncTierFromStripeCustomer(?string $stripeCustomerId): void
    {
        if (! $stripeCustomerId) {
            return;
        }
        $website = Website::query()->where('stripe_id', $stripeCustomerId)->first();
        if (! $website) {
            return;
        }
        $shouldBePro = $website->subscribed('default');
        $newTier = $shouldBePro ? Website::TIER_PRO : Website::TIER_FREE;
        if ($website->tier !== $newTier) {
            $website->forceFill(['tier' => $newTier])->save();
        }
    }
}
