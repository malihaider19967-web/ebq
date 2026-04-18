<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the standalone ebq-intelegence Lighthouse API.
 *
 * Design rules:
 * - Never throw. An audit must complete without CWV data if the service is
 *   down, misconfigured, or times out. Return null and log.
 * - One HTTP call fetches both strategies (mobile + desktop) via /audit/batch.
 *   Keeps the Laravel side simple and lets the Node side reuse Chrome warm
 *   across the two runs.
 * - Normalizes the response to a shape that's trivial to persist in
 *   PageAuditReport::result['core_web_vitals'].
 */
class LighthouseClient
{
    public function isConfigured(): bool
    {
        $url = config('services.lighthouse.url');
        $key = config('services.lighthouse.key');

        return is_string($url) && $url !== ''
            && is_string($key) && $key !== '';
    }

    /**
     * Fetch mobile + desktop CWV for a URL.
     *
     * @return array<string, mixed>|null  Null on any failure — caller stores nothing.
     */
    public function fetchMobileAndDesktop(string $url): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $base = rtrim((string) config('services.lighthouse.url'), '/');
        $key = (string) config('services.lighthouse.key');
        $timeout = (int) config('services.lighthouse.timeout', 90);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->withHeaders([
                    'X-Api-Key' => $key,
                    'Content-Type' => 'application/json',
                ])
                ->post($base.'/audit/batch', [
                    'items' => [
                        ['url' => $url, 'strategy' => 'mobile'],
                        ['url' => $url, 'strategy' => 'desktop'],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LighthouseClient: request failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('LighthouseClient: HTTP '.$response->status(), [
                'url' => $url,
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        try {
            $payload = $response->json();
        } catch (\Throwable $e) {
            Log::warning('LighthouseClient: invalid JSON: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return null;
        }

        $byStrategy = ['mobile' => null, 'desktop' => null];
        foreach ($payload['items'] as $item) {
            if (! is_array($item) || ! ($item['ok'] ?? false) || ! is_array($item['result'] ?? null)) {
                continue;
            }
            $strategy = $item['result']['strategy'] ?? null;
            if ($strategy === 'mobile' || $strategy === 'desktop') {
                $byStrategy[$strategy] = $this->normalizeOne($item['result']);
            }
        }

        // At least one strategy must have succeeded — otherwise storing an empty
        // block is worse than nothing (it hides the "not available" UI branch).
        if ($byStrategy['mobile'] === null && $byStrategy['desktop'] === null) {
            return null;
        }

        return [
            'mobile' => $byStrategy['mobile'],
            'desktop' => $byStrategy['desktop'],
            'fetched_at' => now()->toIso8601String(),
            'source' => 'lighthouse-local',
        ];
    }

    /**
     * Reshape one strategy's payload to exactly the fields the UI + recommender consume.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function normalizeOne(array $r): array
    {
        $cwv = is_array($r['core_web_vitals'] ?? null) ? $r['core_web_vitals'] : [];

        return [
            'performance_score' => $this->intOrNull($r['performance_score'] ?? null),
            'lcp_ms' => $this->intOrNull($cwv['lcp_ms'] ?? null),
            'cls' => $this->floatOrNull($cwv['cls'] ?? null),
            'tbt_ms' => $this->intOrNull($cwv['tbt_ms'] ?? null),
            'fcp_ms' => $this->intOrNull($cwv['fcp_ms'] ?? null),
            'ttfb_ms' => $this->intOrNull($cwv['ttfb_ms'] ?? null),
            'speed_index_ms' => $this->intOrNull($cwv['speed_index_ms'] ?? null),
            'lighthouse_version' => is_string($r['lighthouse_version'] ?? null) ? $r['lighthouse_version'] : null,
            'runtime_error' => is_string($r['runtime_error'] ?? null) ? $r['runtime_error'] : null,
        ];
    }

    private function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) round((float) $v) : null;
    }

    private function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
