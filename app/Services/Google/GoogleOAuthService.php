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
        $data = [
            'access_token' => $googleUser->token,
            'expires_at' => Carbon::now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
        ];

        if ($googleUser->refreshToken) {
            $data['refresh_token'] = $googleUser->refreshToken;
        }

        return GoogleAccount::updateOrCreate(
            ['user_id' => $user->id, 'google_id' => $googleUser->getId()],
            $data,
        );
    }
}
