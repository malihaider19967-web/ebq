<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerperSearchClient
{
    /**
     * @return array<string, mixed>|null Decoded JSON or null on failure / missing key
     */
    public function search(string $query, int $num = 10, ?string $gl = null, ?string $hl = null): ?array
    {
        $key = config('services.serper.key');
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $url = (string) config('services.serper.search_url', 'https://google.serper.dev/search');
        if ($url === '') {
            return null;
        }

        $body = [
            'q' => $query,
            'num' => min(20, max(1, $num)),
        ];
        $glT = is_string($gl) ? strtolower(trim($gl)) : '';
        if ($glT !== '' && strlen($glT) === 2 && ctype_alpha($glT)) {
            $body['gl'] = $glT;
        }
        $hlT = is_string($hl) ? strtolower(trim($hl)) : '';
        if ($hlT !== '' && preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', $hlT) === 1) {
            $body['hl'] = $hlT;
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(8)
                ->withHeaders([
                    'X-API-KEY' => $key,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $body);
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
