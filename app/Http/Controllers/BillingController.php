<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Laravel\Cashier\Exceptions\IncompletePayment;

/**
 * Billing flow — Stripe Checkout + Customer Portal via Cashier.
 *
 * The WordPress plugin's setup wizard sends users here when they pick
 * a paid plan: `/billing/checkout?plan=pro&website_id=12&return_to=...`.
 * We resolve the user's authenticated Website context, call Cashier to
 * mint a Stripe Hosted Checkout session, and redirect.
 *
 * After the Stripe Hosted Checkout completes, Stripe redirects the user
 * to `/billing/success` (success) or `/billing/cancel` (back arrow).
 * Both endpoints respect a `return_to` query parameter so the WP plugin
 * wizard can resume on its own surface.
 *
 * Tier sync happens in two places:
 *   1. Optimistic — `success()` flips Website.tier='pro' immediately so
 *      the user doesn't see a stale "Free" state during the brief
 *      window between Stripe redirect and webhook landing.
 *   2. Authoritative — Stripe webhooks (handled by WebhookController)
 *      update tier on every subscription state transition (cancellation,
 *      payment failure, trial conversion, plan change).
 */
class BillingController extends Controller
{
    /**
     * Mint a Stripe Hosted Checkout session for the chosen plan and
     * redirect. Trial days are read from the Plan row so the marketing
     * page, the WP plugin, and the actual Stripe trial all stay in sync.
     */
    public function checkout(Request $request): RedirectResponse|Response
    {
        $request->validate([
            'plan' => 'required|string|max:32',
            'website_id' => 'nullable|integer',
            'return_to' => 'nullable|string|max:2048',
        ]);

        $plan = Plan::where('slug', $request->string('plan'))
            ->where('is_active', true)
            ->first();
        if (! $plan || ! $plan->isCheckoutReady()) {
            return redirect()->route('pricing')
                ->with('error', 'That plan is not available for purchase right now.');
        }

        $website = $this->resolveBillableWebsite($request);
        if (! $website) {
            return redirect()->route('login', ['redirect_to' => $request->fullUrl()]);
        }

        // Build return URLs so the WP plugin wizard (or any other
        // referrer) gets sent back to the right surface.
        $returnTo = $this->safeReturnUrl($request->input('return_to'));
        $successUrl = route('billing.success', array_filter([
            'website_id' => $website->id,
            'return_to' => $returnTo,
        ]));
        $cancelUrl = route('billing.cancel', array_filter([
            'website_id' => $website->id,
            'return_to' => $returnTo,
        ]));

        try {
            // EBQ only sells yearly subscriptions. The monthly price on
            // the Plan row is for "$X/mo, billed yearly" display copy
            // only — never used to mint a Stripe subscription. Plan::
            // isCheckoutReady() above already verified the yearly price
            // ID is set, so this is safe.
            return $website
                ->newSubscription('default', $plan->stripe_price_id_yearly)
                ->trialDays($plan->trial_days)
                ->checkout([
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ]);
        } catch (IncompletePayment $exception) {
            // Card needs SCA confirmation — Cashier provides a payment
            // confirmation route. For our hosted checkout this is rare
            // (Stripe handles SCA inline) but kept defensively.
            return redirect()->route(
                'cashier.payment',
                [$exception->payment->id, 'redirect' => $cancelUrl]
            );
        }
    }

    /**
     * Stripe redirected back here after successful checkout. We trust
     * the redirect (Stripe signs nothing on this hop) only enough to
     * flip the optimistic tier; the authoritative tier change happens
     * in the webhook handler when Stripe POSTs `customer.subscription.created`.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $website = $this->resolveBillableWebsite($request);
        if (! $website) {
            return redirect()->route('dashboard');
        }

        // Optimistic tier flip — webhook will reconfirm. Idempotent.
        if ($website->tier !== Website::TIER_PRO) {
            $website->forceFill(['tier' => Website::TIER_PRO])->save();
        }

        $returnTo = $this->safeReturnUrl($request->input('return_to'));
        if ($returnTo) {
            return redirect()->away($returnTo.(str_contains($returnTo, '?') ? '&' : '?').'ebq_billing=success');
        }

        // Pay-first flow: when the user paid before doing onboarding, the
        // Website is still a placeholder with no domain. Send them into
        // onboarding so they can hook up their site — onboarding updates
        // this same row, so the subscription stays attached.
        if (trim((string) $website->domain) === '') {
            return redirect()->route('onboarding')->with('status', 'Subscription active — let\'s connect your site.');
        }

        return view('billing.success', [
            'website' => $website,
        ]);
    }

    /**
     * User backed out of the Stripe Hosted Checkout. No state change.
     * Bounce them back to wherever they came from with a "cancelled"
     * marker so the WP wizard can offer "try again" UX.
     */
    public function cancel(Request $request): View|RedirectResponse
    {
        $returnTo = $this->safeReturnUrl($request->input('return_to'));
        if ($returnTo) {
            return redirect()->away($returnTo.(str_contains($returnTo, '?') ? '&' : '?').'ebq_billing=cancelled');
        }

        // Pay-first flow: a freshly-registered user backed out of Stripe
        // Checkout. Their placeholder Website has no domain yet, so don't
        // dump them on a generic "cancelled" page — bring them into
        // onboarding so they can still use the free tier without going
        // through register again.
        $user = $request->user();
        if ($user && ! $user->hasAccessibleWebsites()) {
            return redirect()->route('onboarding')->with('status', 'No subscription started — you can still use the free tier or upgrade later from Billing.');
        }

        return view('billing.cancel');
    }

    /**
     * Open the Stripe Customer Portal so the user can change card,
     * cancel, view invoices, or upgrade/downgrade plan. Cashier signs
     * a one-time URL keyed to the Stripe customer.
     */
    public function portal(Request $request): RedirectResponse
    {
        $website = $this->resolveBillableWebsite($request);
        if (! $website || ! $website->hasStripeId()) {
            return redirect()->route('pricing');
        }

        return $website->redirectToBillingPortal(route('dashboard'));
    }

    /**
     * Resolve which Website is being billed.
     *
     * Priority:
     *   1. `?website_id=N` passed by the WP plugin wizard (must belong to user).
     *   2. The user's first owned website.
     *   3. None — auto-create a placeholder so the pay-first flow (where
     *      a freshly-registered user comes here before onboarding) has
     *      something to attach a Stripe subscription to. The placeholder
     *      has `domain=''`; onboarding fills the domain in afterwards by
     *      updating the same row, keeping the subscription linked.
     */
    private function resolveBillableWebsite(Request $request): ?Website
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $explicit = (int) $request->input('website_id', 0);
        if ($explicit > 0) {
            return Website::query()
                ->where('id', $explicit)
                ->where('user_id', $user->id)
                ->first();
        }

        $existing = Website::query()->where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }

        return Website::create([
            'user_id' => $user->id,
            'domain' => '',
            'tier' => Website::TIER_FREE,
        ]);
    }

    /**
     * Sanitise a `return_to` URL — only allow http(s) URLs to prevent
     * open-redirect to javascript:/data: schemes. Caller is expected
     * to trust the host (typically a customer's WordPress install) so
     * we don't restrict the host.
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
