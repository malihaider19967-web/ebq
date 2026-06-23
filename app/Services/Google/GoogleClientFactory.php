<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

class GoogleClientFactory
{
    public function make(GoogleAccount $account): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        // Google\Client's default HTTP client has no read timeout — a stalled
        // response on a large account (e.g. Search Analytics on a huge property)
        // can block curl_exec() indefinitely, and the queue job's own $timeout
        // (pcntl SIGALRM) doesn't reliably interrupt a blocking libcurl read.
        // An explicit client-side timeout turns that into a catchable/retryable
        // failure instead of an unbounded hang. See infra/data-sources/sync-jobs.md.
        $client->setHttpClient(new GuzzleClient(['connect_timeout' => 10, 'timeout' => 120]));

        $token = [
            'access_token' => $account->access_token,
            'expires_in' => $account->expires_at
                ? max(0, now()->diffInSeconds($account->expires_at, false))
                : 0,
        ];

        if ($account->refresh_token) {
            $token['refresh_token'] = $account->refresh_token;
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            if (! $account->refresh_token) {
                throw new \RuntimeException(
                    'Google access token expired and no refresh token available. Please reconnect your Google account in Settings.'
                );
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

            if (isset($newToken['error'])) {
                Log::error('Google token refresh failed', $newToken);
                throw new \RuntimeException(
                    'Failed to refresh Google token: ' . ($newToken['error_description'] ?? $newToken['error'])
                    . '. Please reconnect your Google account in Settings.'
                );
            }

            $account->update([
                'access_token' => $newToken['access_token'],
                'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);

            $client->setAccessToken($newToken);
        }

        return $client;
    }
}
