<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerperSearchClient
{
    /**
     * @return array<string, mixed>|null Decoded JSON or null on failure / missing key
     */
    public function search(string $query, int $num = 10): ?array
    {
        $key = config('services.serper.key');
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $url = (string) config('services.serper.search_url', 'https://google.serper.dev/search');
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(8)
                ->withHeaders([
                    'X-API-KEY' => $key,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'q' => $query,
                    'num' => min(20, max(1, $num)),
                ]);
        } catch (\Throwable $e) {
            Log::warning('SerperSearchClient: request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('SerperSearchClient: HTTP '.$response->status());

            return null;
        }

        try {
            $json = $response->json();
        } catch (\Throwable $e) {
            Log::warning('SerperSearchClient: invalid JSON body: '.$e->getMessage());

            return null;
        }

        if (! is_array($json)) {
            return null;
        }

        return $json;
    }
}
