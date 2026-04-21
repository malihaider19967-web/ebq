<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebsiteVerificationController extends Controller
{
    /**
     * Called from the EBQ UI (session auth) to mint a fresh challenge code for
     * a website the user owns. Returns the code — the user pastes it into the
     * WP plugin settings, which serves it at /.well-known/ebq-verification.txt.
     */
    public function challenge(Request $request): JsonResponse
    {
        $websiteId = (int) $request->input('website_id');
        $user = Auth::user();

        if (! $user || ! $user->canViewWebsiteId($websiteId)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $website = Website::findOrFail($websiteId);
        $verification = WebsiteVerification::issueFor($website);

        return response()->json([
            'challenge_code' => $verification->challenge_code,
            'expires_at' => $verification->expires_at->toIso8601String(),
            'verify_path' => '/.well-known/ebq-verification.txt',
            'next_step' => 'Click Verify in the WP plugin (or POST /api/v1/verify/confirm with website_id).',
        ]);
    }

    /**
     * Called either by the EBQ UI (after the user clicks "Verify now") or by
     * the WP plugin itself. Fetches the well-known URL, matches the code,
     * marks the verification, and mints a long-lived Sanctum token for the
     * Website.
     */
    public function confirm(Request $request): JsonResponse
    {
        $websiteId = (int) $request->input('website_id');
        $tokenName = (string) ($request->input('token_name') ?: 'WordPress plugin');

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($websiteId)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $website = Website::findOrFail($websiteId);
        $verification = WebsiteVerification::query()
            ->where('website_id', $website->id)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (! $verification) {
            return response()->json(['error' => 'no_active_challenge'], 422);
        }

        $verification->forceFill(['last_attempt_at' => Carbon::now()])->save();

        $targetUrl = rtrim('https://'.$website->domain, '/').'/.well-known/ebq-verification.txt';
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'EBQ-Verifier/1.0'])
                ->get($targetUrl);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'fetch_failed',
                'reason' => $e->getMessage(),
                'url' => $targetUrl,
            ], 422);
        }

        if (! $response->successful()) {
            return response()->json([
                'error' => 'non_2xx',
                'status' => $response->status(),
                'url' => $targetUrl,
            ], 422);
        }

        $body = trim((string) $response->body());
        if ($body !== $verification->challenge_code) {
            return response()->json([
                'error' => 'mismatch',
                'hint' => 'Body did not equal the expected challenge code.',
            ], 422);
        }

        $verification->forceFill(['verified_at' => Carbon::now()])->save();

        // Sanctum token — the plain text is only returned here, once.
        $newToken = $website->createToken($tokenName, ['read:insights']);

        return response()->json([
            'token' => $newToken->plainTextToken,
            'website_id' => $website->id,
            'abilities' => ['read:insights'],
            'issued_at' => Carbon::now()->toIso8601String(),
        ]);
    }
}
