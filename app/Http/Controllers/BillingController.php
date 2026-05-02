<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Exceptions\IncompletePayment;

/**
 * Billing flow — Stripe Checkout + Customer Portal + in-app subscription
 * management via Cashier. Billing is per-USER (a single subscription
 * caps how many websites the user can manage; over-limit sites freeze
 * read-only via App\Models\User::frozenWebsiteIds()).
 *
 * Public surfaces:
 *
 *   GET  /billing                  show()                Subscription page
 *   POST /billing/swap             swap()                Switch plan (Cashier swap, prorated)
 *   POST /billing/cancel           cancelSubscription()  Cancel at period end
 *   POST /billing/resume           resume()              Undo a pending cancel
 *   GET  /billing/checkout         checkout()            Stripe Hosted Checkout
 *   GET  /billing/success          success()             Stripe redirected back, success
 *   GET  /billing/cancel-checkout  cancel()              Stripe redirected back, user backed out
 *   GET  /billing/portal           portal()              Stripe Customer Portal (cards / invoices)
 *
 * Tier sync runs in two places:
 *   1. Optimistic — `success()` writes `User->current_plan_slug` immediately
 *      so the user doesn't see a stale state during the brief window between
 *      the Stripe redirect and the webhook landing.
 *   2. Authoritative — `StripeWebhookController` handles every subscription
 *      lifecycle event (created / updated / deleted) and is the source of
 *      truth.
 */
class BillingController extends Controller
{
    /**
     * Mint a Stripe Hosted Checkout session for the chosen plan and
     * redirect. Trial days are read from the Plan row so the marketing
     * page, the WP plugin wizard, and the actual Stripe trial all stay in sync.
     */
    public function checkout(Request $request): RedirectResponse|Response|Checkout
    {
        $request->validate([
            'plan' => 'required|string|max:32',
            'return_to' => 'nullable|string|max:2048',
        ]);

        $plan = Plan::where('slug', $request->string('plan'))
            ->where('is_active', true)
            ->first();
        if (! $plan || ! $plan->isCheckoutReady()) {
            return redirect()->route('pricing')
                ->with('error', 'That plan is not available for purchase right now.');
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login', ['redirect_to' => $request->fullUrl()]);
        }

        // If the user is already actively subscribed, route them through
        // the in-app swap flow rather than minting a second subscription
        // (Cashier would happily create one but Stripe would charge twice).
        if ($user->subscribed('default')) {
            return redirect()->route('billing.show')
                ->with('status', 'You already have an active subscription. Use "Switch plan" below to change tiers.');
        }

        // Build return URLs so the WP plugin wizard (or any other
        // referrer) gets sent back to the right surface.
        $returnTo = $this->safeReturnUrl($request->input('return_to'));
        $successUrl = route('billing.success', array_filter(['return_to' => $returnTo]));
        $cancelUrl = route('billing.cancel-checkout', array_filter(['return_to' => $returnTo]));

        try {
            // EBQ only sells yearly subscriptions. The monthly price on
            // the Plan row is for "$X/mo, billed yearly" display copy
            // only — never used to mint a Stripe subscription.
            return $user
                ->newSubscription('default', $plan->stripe_price_id_yearly)
                ->trialDays($plan->trial_days)
                ->checkout([
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ]);
        } catch (IncompletePayment $exception) {
            return redirect()->route(
                'cashier.payment',
                [$exception->payment->id, 'redirect' => $cancelUrl]
            );
        }
    }

    /**
     * Stripe redirected back after successful checkout. Optimistically
     * snapshot the plan slug; webhook will reconfirm.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('dashboard');
        }

        // Optimistic plan-slug snapshot. Idempotent — the webhook
        // overwrites with the same value moments later.
        $subscription = $user->subscription('default');
        if ($subscription && $subscription->valid()) {
            $plan = Plan::where('stripe_price_id_yearly', $subscription->stripe_price)->first();
            if ($plan && $user->current_plan_slug !== $plan->slug) {
                $user->forceFill(['current_plan_slug' => $plan->slug])->save();
            }
        }

        $returnTo = $this->safeReturnUrl($request->input('return_to'));
        if ($returnTo) {
            return redirect()->away($returnTo.(str_contains($returnTo, '?') ? '&' : '?').'ebq_billing=success');
        }

        // Pay-first flow: a freshly-registered user paid before doing
        // onboarding. Bounce to onboarding so they can connect their
        // site — `current_website_id` is empty / no domain yet.
        if (! $user->hasAccessibleWebsites()) {
            return redirect()->route('onboarding')->with('status', 'Subscription active — let\'s connect your site.');
        }

        return view('billing.success', [
            'user' => $user,
        ]);
    }

    /**
     * User backed out of the Stripe Hosted Checkout. No state change.
     */
    public function cancel(Request $request): View|RedirectResponse
    {
        $returnTo = $this->safeReturnUrl($request->input('return_to'));
        if ($returnTo) {
            return redirect()->away($returnTo.(str_contains($returnTo, '?') ? '&' : '?').'ebq_billing=cancelled');
        }

        $user = $request->user();
        if ($user && ! $user->hasAccessibleWebsites()) {
            return redirect()->route('onboarding')->with('status', 'No subscription started — you can still use the free tier or upgrade later from Billing.');
        }

        return view('billing.cancel');
    }

    /**
     * Stripe Customer Portal — change card, view invoices, cancel.
     * Kept as the secondary action; primary plan-management lives on
     * the in-app subscription page.
     */
    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasStripeId()) {
            return redirect()->route('billing.show');
        }
        return $user->redirectToBillingPortal(route('billing.show'));
    }

    /* ───── In-app subscription management (Workstream B) ───── */

    /**
     * Render the Subscription page — current plan + plan grid +
     * cancel / resume + recent invoices + frozen-sites banner.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        return view('billing.subscription');
    }

    /**
     * Switch plan via Cashier `swap()` (immediate + Stripe-prorated).
     * Idempotent: swapping to the current plan is a no-op (Cashier
     * handles this).
     */
    public function swap(Request $request): RedirectResponse
    {
        $request->validate([
            'plan' => 'required|string|max:32',
        ]);

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $plan = Plan::where('slug', $request->string('plan'))
            ->where('is_active', true)
            ->first();
        if (! $plan || ! $plan->isCheckoutReady()) {
            return redirect()->route('billing.show')
                ->with('error', 'That plan is not available right now.');
        }

        $subscription = $user->subscription('default');
        if (! $subscription || ! $subscription->valid()) {
            // No active subscription — route into the standard checkout
            // flow instead of trying to swap a non-existent sub.
            return redirect()->route('billing.checkout', ['plan' => $plan->slug]);
        }

        try {
            $subscription->swap($plan->stripe_price_id_yearly);
        } catch (\Throwable $e) {
            return redirect()->route('billing.show')
                ->with('error', 'Could not switch plan: '.$e->getMessage());
        }

        // Snapshot the new plan slug right away. Webhook reconfirms.
        $user->forceFill(['current_plan_slug' => $plan->slug])->save();

        return redirect()->route('billing.show')
            ->with('status', 'Switched to '.$plan->name.'. Stripe applied a prorated charge or credit automatically.');
    }

    /**
     * Cancel at period end. Pro access continues until the current
     * billing window closes; on that date the webhook fires
     * `customer.subscription.deleted` and tier flips to free.
     */
    public function cancelSubscription(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $subscription = $user->subscription('default');
        if (! $subscription || ! $subscription->valid()) {
            return redirect()->route('billing.show')
                ->with('error', 'No active subscription to cancel.');
        }

        try {
            $subscription->cancel();
        } catch (\Throwable $e) {
            return redirect()->route('billing.show')
                ->with('error', 'Could not cancel: '.$e->getMessage());
        }

        $endsAt = $subscription->fresh()?->ends_at;
        $endsAtLabel = $endsAt ? $endsAt->toFormattedDayDateString() : 'the end of the current period';

        return redirect()->route('billing.show')
            ->with('status', 'Subscription cancelled. You\'ll keep Pro access until '.$endsAtLabel.'.');
    }

    /**
     * Resume a cancelled-but-still-in-grace subscription. Cashier
     * `resume()` clears `ends_at` on the local subscription row and
     * tells Stripe to keep billing.
     */
    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $subscription = $user->subscription('default');
        if (! $subscription || ! $subscription->onGracePeriod()) {
            return redirect()->route('billing.show')
                ->with('error', 'No cancelled subscription to resume.');
        }

        try {
            $subscription->resume();
        } catch (\Throwable $e) {
            return redirect()->route('billing.show')
                ->with('error', 'Could not resume: '.$e->getMessage());
        }

        return redirect()->route('billing.show')
            ->with('status', 'Subscription resumed.');
    }

    /**
     * Sanitise a `return_to` URL — only allow http(s) to prevent
     * open-redirect to javascript:/data: schemes.
     */
    private function safeReturnUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $url = trim($url);
        if (! preg_match('/^https?:\/\//i', $url)) {
            return null;
        }
        return $url;
    }
}
