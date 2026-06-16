<?php

namespace App\Http\Controllers;

use App\Jobs\RunGuestRankCheck;
use App\Models\GuestRankCheck;
use App\Models\Lead;
use App\Rules\ValidRecaptcha;
use App\Support\Audit\SerpGlCatalog;
use App\Support\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Public, no-signup keyword rank tracker driven from the marketing site.
 *
 * Same progressive friction as {@see GuestPageSpeedController}, counted per
 * browser via a signed cookie:
 *   check #1  → free, shown on screen
 *   check #2  → require name + email, delivered by email (not shown)
 *   check #3+ → block, push free-plan signup
 */
class GuestRankCheckController extends Controller
{
    private const PER_MINUTE = 4;

    private const PER_DAY = 15;

    private const COUNT_COOKIE = 'ebq_guest_rank';

    private const COUNT_COOKIE_MINUTES = 525600; // ~1 year

    public function store(Request $request): JsonResponse
    {
        $ip = (string) $request->ip();

        $minuteKey = 'guest-rank:m:'.$ip;
        $dayKey = 'guest-rank:d:'.$ip;
        if (RateLimiter::tooManyAttempts($minuteKey, self::PER_MINUTE) || RateLimiter::tooManyAttempts($dayKey, self::PER_DAY)) {
            return response()->json([
                'message' => 'You’ve run a lot of checks in a short time. Please wait a moment and try again.',
            ], 429);
        }

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:200'],
            'domain' => ['required', 'string', 'max:255'],
        ]);

        // Reduce the domain input ("https://www.example.com/path", "example.com")
        // to a bare host. A non-host (no dot) is almost always a typo.
        $domain = $this->normalizeDomain($validated['domain']);
        if ($domain === '' || ! str_contains($domain, '.')) {
            return response()->json([
                'message' => 'Enter a valid domain, like example.com.',
                'errors' => ['domain' => ['Enter a valid domain, like example.com.']],
            ], 422);
        }

        // Optional SERP country (gl). Empty = Serper's default. A non-empty value
        // comes from our own <select>, so an invalid one means tampering.
        $gl = strtolower(trim((string) $request->input('country', '')));
        if ($gl === '') {
            $gl = null;
        } elseif (! array_key_exists($gl, SerpGlCatalog::selectOptions())) {
            return response()->json([
                'message' => 'Please choose a valid country.',
                'errors' => ['country' => ['Please choose a valid country.']],
            ], 422);
        }

        if (! is_string(config('services.serper.key')) || trim((string) config('services.serper.key')) === '') {
            return response()->json(['message' => 'Rank tracking is temporarily unavailable. Please try again shortly.'], 503);
        }

        // Progressive friction by browser. $prior = checks already run from this
        // browser; this attempt is the ($prior + 1)th.
        $prior = max(0, (int) $request->cookie(self::COUNT_COOKIE, 0));
        $attempt = $prior + 1;

        // 3rd+ → block, push signup.
        if ($attempt >= 3) {
            return response()->json([
                'require' => 'signup',
                'message' => 'You’ve used your free rank checks. Create a free account to keep tracking — no credit card — and unlock continuous rank tracking across keywords, devices and countries.',
                'register_url' => route('register'),
            ]);
        }

        // 2nd → require name + email; delivered by email (not shown on screen).
        // Ask before validating the captcha so this round-trip doesn't burn it.
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

        $row = GuestRankCheck::start($validated['keyword'], $domain, $gl, $ip, $email, $name);
        RunGuestRankCheck::dispatch($row->id);

        // 2nd check → delivered by email only; confirm on screen, do NOT reveal.
        if ($email !== null) {
            Lead::capture($email, $name, null, Lead::SOURCE_GUEST_RANK);

            return response()->json([
                'token' => $row->token,
                'emailed' => true,
                'email' => $email,
                'message' => "We’ve emailed your rank report to {$email}. It lands in a minute — check your inbox (and spam, just in case).",
            ], 202)->cookie(self::COUNT_COOKIE, (string) $attempt, self::COUNT_COOKIE_MINUTES);
        }

        // 1st check → shown on screen.
        return response()->json([
            'token' => $row->token,
            'status_url' => route('guest-rank.status', $row),
            'results_url' => route('guest-rank.show', $row),
            'emailed' => false,
        ], 202)->cookie(self::COUNT_COOKIE, (string) $attempt, self::COUNT_COOKIE_MINUTES);
    }

    public function status(GuestRankCheck $guestRankCheck): JsonResponse
    {
        return response()->json([
            'status' => $guestRankCheck->status,
            'results_url' => route('guest-rank.show', $guestRankCheck),
        ]);
    }

    public function show(GuestRankCheck $guestRankCheck): View
    {
        return view('guest-rank-check.show', ['report' => $guestRankCheck]);
    }

    /** Reduce a URL or bare host to a comparable registrable host (no scheme/www). */
    private function normalizeDomain(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (! str_contains($value, '://')) {
            $value = 'http://'.$value;
        }
        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host)) {
            return '';
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
