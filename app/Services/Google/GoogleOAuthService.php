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

        // Capture the account email so source pickers can show which
        // login owns each GA/GSC property. Only overwrite when Google
        // returns one (the data-sync flow omits the email scope).
        $email = trim((string) ($googleUser->getEmail() ?? ''));
        if ($email !== '') {
            $data['email'] = $email;
        }

        if ($googleUser->refreshToken) {
            $data['refresh_token'] = $googleUser->refreshToken;
        }

        return GoogleAccount::updateOrCreate(
            ['user_id' => $user->id, 'google_id' => $googleUser->getId()],
            $data,
        );
    }
}
