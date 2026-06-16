<?php

namespace App\Http\Controllers;

use App\Jobs\RunGuestKeywordVolume;
use App\Models\GuestKeywordVolume;
use App\Models\Lead;
use App\Rules\ValidRecaptcha;
use App\Support\KeywordsEverywhereCountries;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Public, no-signup keyword search-volume finder driven from the marketing
 * site.
 *
 * Same progressive friction as {@see GuestRankCheckController}, counted per
 * browser via a signed cookie:
 *   check #1  → free, shown on screen
 *   check #2  → require name + email, delivered by email (not shown)
 *   check #3+ → block, push free-plan signup
 *
 * One keyword per check (the freemium contract); the portal multi-keyword
 * finder is the upsell.
 */
class GuestKeywordVolumeController extends Controller
{
    private const PER_MINUTE = 5;

    private const PER_DAY = 20;

    private const COUNT_COOKIE = 'ebq_guest_volume';

    private const COUNT_COOKIE_MINUTES = 525600; // ~1 year

    public function store(Request $request): JsonResponse
    {
        $ip = (string) $request->ip();

        $minuteKey = 'guest-volume:m:'.$ip;
        $dayKey = 'guest-volume:d:'.$ip;
        if (RateLimiter::tooManyAttempts($minuteKey, self::PER_MINUTE) || RateLimiter::tooManyAttempts($dayKey, self::PER_DAY)) {
            return response()->json([
                'message' => 'You’ve run a lot of checks in a short time. Please wait a moment and try again.',
            ], 429);
        }

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:200'],
        ]);
        $keyword = trim($validated['keyword']);
        if ($keyword === '') {
            return response()->json([
                'message' => 'Enter a keyword to check.',
                'errors' => ['keyword' => ['Enter a keyword to check.']],
            ], 422);
        }

        // KE only supports a short country list — reject anything else (it
        // comes from our own <select>, so an invalid value means tampering).
        $country = strtolower(trim((string) $request->input('country', 'global'))) ?: 'global';
        if (! KeywordsEverywhereCountries::isValid($country)) {
            return response()->json([
                'message' => 'Please choose a valid country.',
                'errors' => ['country' => ['Please choose a valid country.']],
            ], 422);
        }

        if (! is_string(config('services.keywords_everywhere.key')) || trim((string) config('services.keywords_everywhere.key')) === '') {
            return response()->json(['message' => 'Keyword volume lookups are temporarily unavailable. Please try again shortly.'], 503);
        }

        // Progressive friction by browser. $prior = checks already run from this
        // browser; this attempt is the ($prior + 1)th.
        $prior = max(0, (int) $request->cookie(self::COUNT_COOKIE, 0));
        $attempt = $prior + 1;

        // 3rd+ → block, push signup.
        if ($attempt >= 3) {
            return response()->json([
                'require' => 'signup',
                'message' => 'You’ve used your free volume checks. Create a free account to keep researching — no credit card — and unlock bulk volume lookups, CPC, competition and 12-month trends across your whole keyword set.',
                'register_url' => route('register'),
            ]);
        }

        // 2nd → require name + email; delivered by email (not shown on screen).
        $email = null;
        $name = null;
        if ($attempt >= 2) {
            $email = trim((string) $request->input('email', ''));
            if ($email === '') {
                return response()->json([
                    'require' => 'email',
                    'message' => 'This is your last free check — tell us where to send it.',
                ]);
            }
            $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255'],
            ]);
            $name = trim((string) $request->input('name', ''));
        }

        // Validate the captcha exactly once, only on an otherwise-valid submit.
        if (Recaptcha::isEnabled()) {
            $request->validate(['g-recaptcha-response' => ['required', 'string', new ValidRecaptcha]]);
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($dayKey, 86400);

        $row = GuestKeywordVolume::start($keyword, $country, $ip, $email, $name);
        RunGuestKeywordVolume::dispatch($row->id);

        // 2nd check → delivered by email only; confirm on screen, do NOT reveal.
        if ($email !== null) {
            Lead::capture($email, $name, null, Lead::SOURCE_GUEST_VOLUME);

            return response()->json([
                'token' => $row->token,
                'emailed' => true,
                'email' => $email,
                'message' => "We’ve emailed your keyword volume report to {$email}. It lands in a minute — check your inbox (and spam, just in case).",
            ], 202)->cookie(self::COUNT_COOKIE, (string) $attempt, self::COUNT_COOKIE_MINUTES);
        }

        // 1st check → shown on screen.
        return response()->json([
            'token' => $row->token,
            'status_url' => route('guest-volume.status', $row),
            'results_url' => route('guest-volume.show', $row),
            'emailed' => false,
        ], 202)->cookie(self::COUNT_COOKIE, (string) $attempt, self::COUNT_COOKIE_MINUTES);
    }

    public function status(GuestKeywordVolume $guestKeywordVolume): JsonResponse
    {
        return response()->json([
            'status' => $guestKeywordVolume->status,
            'results_url' => route('guest-volume.show', $guestKeywordVolume),
        ]);
    }

    public function show(GuestKeywordVolume $guestKeywordVolume): View
    {
        return view('guest-keyword-volume.show', ['report' => $guestKeywordVolume]);
    }
}
