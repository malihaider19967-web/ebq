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
        $client->setAccessToken($account->access_token);

        if ($account->expires_at?->isPast() && $account->refresh_token) {
            $client->refreshToken($account->refresh_token);

            $account->update([
                'access_token' => $client->getAccessToken()['access_token'],
                'expires_at' => now()->addSeconds($client->getAccessToken()['expires_in'] ?? 3600),
            ]);
        }

        return $client;
    }
}
