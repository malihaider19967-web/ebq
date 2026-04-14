<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleOAuthService
{
    public function persistAccount(User $user, SocialiteUser $googleUser): GoogleAccount
    {
        return GoogleAccount::updateOrCreate(
            ['user_id' => $user->id, 'google_id' => $googleUser->getId()],
            [
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'expires_at' => Carbon::now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
            ],
        );
    }
}
