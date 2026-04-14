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
        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/analytics.readonly',
                'https://www.googleapis.com/auth/webmasters.readonly',
            ])
            ->redirect();
    }

    public function callback(GoogleOAuthService $oauthService): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $oauthService->persistAccount(Auth::user(), $googleUser);

        return redirect()->route('onboarding');
    }
}
