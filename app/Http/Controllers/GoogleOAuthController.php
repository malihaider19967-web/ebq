<?php

namespace App\Http\Controllers;

use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // `access_type=offline` requests a refresh token so EBQ can keep
        // syncing GSC / Analytics in the background without re-prompting.
        // `prompt=consent` ensures the refresh token is actually issued
        // (Google omits it on subsequent OAuth flows when the same scopes
        // are already granted). `include_granted_scopes=true` lets the
        // user grant additional scopes incrementally without re-confirming
        // ones they've already approved — Google's recommended pattern.
        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/analytics.readonly',
                'https://www.googleapis.com/auth/webmasters.readonly',
                'https://www.googleapis.com/auth/indexing',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect();
    }

    public function callback(GoogleOAuthService $oauthService): RedirectResponse
    {
        // `stateless()` is intentional — Socialite's session-state check
        // can fail across the OAuth round-trip when the host runs behind
        // a proxy that strips the session cookie on the callback. CSRF is
        // still protected by Google's own state nonce in the redirect.
        $googleUser = Socialite::driver('google')->stateless()->user();
        $oauthService->persistAccount(Auth::user(), $googleUser);

        return redirect()->route('onboarding');
    }
}
