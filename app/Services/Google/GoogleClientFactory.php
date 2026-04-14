<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Client as GoogleClient;

class GoogleClientFactory
{
    public function make(GoogleAccount $account): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        $token = [
            'access_token' => $account->access_token,
            'expires_in' => $account->expires_at ? now()->diffInSeconds($account->expires_at, false) : 3600,
        ];

        if ($account->refresh_token) {
            $token['refresh_token'] = $account->refresh_token;
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired() && $account->refresh_token) {
            $client->fetchAccessTokenWithRefreshToken($account->refresh_token);
            $newToken = $client->getAccessToken();

            $account->update([
                'access_token' => $newToken['access_token'],
                'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);
        }

        return $client;
    }
}
