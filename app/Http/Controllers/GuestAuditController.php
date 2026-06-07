<?php

namespace App\Http\Controllers;

use App\Jobs\RunGuestPageAudit;
use App\Models\GuestPageAudit;
use App\Models\Lead;
use App\Rules\ValidRecaptcha;
use App\Support\Audit\SafeHttpGuard;
use App\Support\Audit\SerpGlCatalog;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Public, no-signup SEO audit driven from the marketing landing page.
 *
 * A visitor submits a URL + keyword; we queue a {@see RunGuestPageAudit} job
 * (lite, no GSC/GA, no paid Serper/Lighthouse) and hand back an unguessable
 * token. The browser polls {@see status()} and lands on {@see show()} — which
 * renders the report and upsells the full GSC/GA-powered audit.
 */
class GuestAuditController extends Controller
{
    /** Per-IP throttle: short burst window + daily ceiling. */
    private const PER_MINUTE = 5;

    private const PER_DAY = 20;

    /**
     * Progressive friction, counted per-browser via a signed cookie:
     *   audit #1  → free, no email
     *   audit #2  → require email, then email the report link
     *   audit #3+ → block, push free-plan signup
     */
    private const COUNT_COOKIE = 'ebq_guest_audits';

    private const COUNT_COOKIE_MINUTES = 525600; // ~1 year

    public function store(Request $request, SafeHttpGuard $guard): JsonResponse
    {
        $ip = (string) $request->ip();

        $minuteKey = 'guest-audit:m:'.$ip;
        $dayKey = 'guest-audit:d:'.$ip;
        if (RateLimiter::tooManyAttempts($minuteKey, self::PER_MINUTE) || RateLimiter::tooManyAttempts($dayKey, self::PER_DAY)) {
            return response()->json([
                'message' => 'You’ve run a lot of audits in a short time. Please wait a moment and try again.',
            ], 429);
        }

        // Normalize before validation: accept "example.com" → "https://example.com".
        $rawUrl = trim((string) $request->input('url', ''));
        if ($rawUrl !== '' && ! preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'https://'.$rawUrl;
        }
        $request->merge(['url' => $rawUrl]);

        // Only URL + keyword are validated up front. The reCAPTCHA is validated
        // *later* — exactly once, on the request that actually runs an audit — so
        // the "we need your email" round-trip doesn't consume (and re-prompt) it.
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:700'],
            'keyword' => ['required', 'string', 'max:200'],
        ]);

        // Optional SERP country (gl). Empty = auto-detect from the page locale.
        // A non-empty value comes from our own <select>, so an invalid one means
        // tampering — reject it rather than silently ignoring.
        $gl = strtolower(trim((string) $request->input('country', '')));
        if ($gl === '') {
            $gl = null;
        } elseif (! array_key_exists($gl, SerpGlCatalog::selectOptions())) {
            return response()->json([
                'message' => 'Please choose a valid country.',
                'errors' => ['country' => ['Please choose a valid country.']],
            ], 422);
        }

        // SSRF / unsafe-target rejection before we create a row or spend a worker.
        $check = $guard->check($validated['url']);
        if (! ($check['ok'] ?? false)) {
            return response()->json([
                'message' => 'That URL can’t be audited. Enter a public website address (https://…).',
                'errors' => ['url' => ['That URL can’t be audited. Enter a public website address (https://…).']],
            ], 422);
        }

        // Progressive friction by browser. $prior = audits already run from this
        // browser; this attempt is the ($prior + 1)th.
        $prior = max(0, (int) $request->cookie(self::COUNT_COOKIE, 0));
        $attempt = $prior + 1;

        // 3rd+ audit → block and push free signup (no audit runs, no captcha, no rate-limit hit).
        if ($attempt >= 3) {
            return response()->json([
                'require' => 'signup',
                'message' => 'You’ve used your free audits. Create a free account to keep auditing — no credit card, and you’ll unlock live keyword positions, Core Web Vitals, and continuous tracking once you connect your site.',
                'register_url' => route('register'),
            ]);
        }

        // 2nd audit → require name + email; the report is delivered to that
        // email (not shown on screen). Ask for the email *before* validating the
        // captcha so this round-trip doesn't burn the token.
        $email = null;
        $name = null;
        if ($attempt >= 2) {
            $email = trim((string) $request->input('email', ''));
            if ($email === '') {
                return response()->json([
                    'require' => 'email',
                    'message' => 'This is your last free audit — tell us where to send it.',
                ]);
            }
            // Validate name + email *before* the captcha so a typo here never
            // consumes the single-use captcha token (no re-prompt on retry).
            $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255'],
            ]);
            $name = trim((string) $request->input('name', ''));
        }

        // Final check before running — validate the captcha exactly once, only on
        // an otherwise-valid submit, so it's never re-run by an earlier round-trip.
        if (Recaptcha::isEnabled()) {
            $request->validate(['g-recaptcha-response' => ['required', 'string', new ValidRecaptcha]]);
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($dayKey, 86400);

        $audit = GuestPageAudit::start($validated['url'], $validated['keyword'], $ip, $gl, $email, $name);
        RunGuestPageAudit::dispatch($audit->id);

        // Capture the marketing lead (name + email) on the email-gated audit.
        if ($email !== null) {
            Lead::capture($email, $name, $audit->id);
        }

        // 2nd audit → delivered by email only; confirm on screen, do NOT reveal results.
        if ($email !== null) {
            return response()->json([
                'token' => $audit->token,
                'emailed' => true,
                'email' => $email,
                'message' => "We’ve emailed your audit to {$email}. It lands in a minute — check your inbox (and spam, just in case).",
            ], 202)->cookie(self::COUNT_COOKIE, (string) $attempt, self::COUNT_COOKIE_MINUTES);
        }

        // 1st audit → shown on screen.
        return response()->json([
            'token' => $audit->token,
            'status_url' => route('guest-audit.status', $audit),
            'results_url' => route('guest-audit.show', $audit),
            'emailed' => false,
        ], 202)->cookie(self::COUNT_COOKIE, (string) $attempt, self::COUNT_COOKIE_MINUTES);
    }

    /** Lightweight poll target for the results page / hero JS. */
    public function status(GuestPageAudit $guestPageAudit): JsonResponse
    {
        return response()->json([
            'status' => $guestPageAudit->status,
            'results_url' => route('guest-audit.show', $guestPageAudit),
        ]);
    }

    public function show(GuestPageAudit $guestPageAudit): View
    {
        return view('guest-audit.show', ['audit' => $guestPageAudit]);
    }
}
