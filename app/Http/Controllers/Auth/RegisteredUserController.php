<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Models\WebsiteInvitation;
use App\Rules\ValidRecaptcha;
use App\Support\Recaptcha;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $inviteToken = (string) $request->query('invite', '');
        $invitationEmail = '';
        if ($inviteToken !== '') {
            $invitation = WebsiteInvitation::findValidByPlainToken($inviteToken);
            if ($invitation) {
                $invitationEmail = $invitation->email;
            }
        }

        // Carry the plan slug from the /pricing CTA (`/register?plan=pro`)
        // through register → store() → billing checkout. Stored in session
        // so it survives the form POST without being a hidden field that
        // an attacker could swap for a different plan slug client-side.
        $planSlug = $this->capturePendingPlan($request);

        return view('auth.register', [
            'inviteToken' => $inviteToken,
            'invitationEmail' => $invitationEmail,
            'pendingPlan' => $planSlug,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'invite' => ['nullable', 'string', 'max:128'],
        ];

        if (Recaptcha::isEnabled()) {
            $rules['g-recaptcha-response'] = ['required', 'string', new ValidRecaptcha];
        }

        $validated = $request->validate($rules);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        WebsiteInvitation::query()
            ->where('email', Str::lower($validated['email']))
            ->where('expires_at', '>', now())
            ->get()
            ->each(fn (WebsiteInvitation $invitation) => $invitation->acceptFor($user));

        event(new Registered($user));

        Auth::login($user);

        // Pay-first flow: when the user picked a paid plan on /pricing,
        // jump straight to Stripe Checkout. BillingController auto-creates
        // a placeholder Website to attach the subscription to, and after
        // Stripe success the user lands on /onboarding to fill in their
        // real domain — which UPDATES the placeholder so the subscription
        // stays linked to the same row. Email verification still happens
        // later via the standard verified-route middleware; we don't gate
        // checkout on it (Stripe collects a verified email of its own).
        $pendingPlan = (string) $request->session()->pull('pending_plan', '');
        if ($pendingPlan !== '' && $this->isCheckoutablePlan($pendingPlan)) {
            return redirect()->route('billing.checkout', ['plan' => $pendingPlan]);
        }

        return redirect()->route('verification.notice');
    }

    /**
     * Read `?plan=` from the request, validate against an active plan, and
     * stash it in session so the subsequent POST → store() can pick it up.
     * Returns the slug (or '') so the view can show a "you'll be billed
     * for the X plan after sign-up" hint.
     */
    private function capturePendingPlan(Request $request): string
    {
        $slug = trim((string) $request->query('plan', ''));
        if ($slug === '') {
            return (string) $request->session()->get('pending_plan', '');
        }
        if (! $this->isCheckoutablePlan($slug)) {
            return '';
        }
        $request->session()->put('pending_plan', $slug);

        return $slug;
    }

    /**
     * True when `$slug` matches an active, checkout-ready paid plan. Free
     * tiers and unknown slugs return false so we never redirect register
     * through a broken Stripe session.
     */
    private function isCheckoutablePlan(string $slug): bool
    {
        if ($slug === '' || $slug === 'free') {
            return false;
        }
        $plan = Plan::where('slug', $slug)->where('is_active', true)->first();

        return $plan !== null && $plan->isCheckoutReady();
    }
}
