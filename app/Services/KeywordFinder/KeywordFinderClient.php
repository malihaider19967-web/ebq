<?php

namespace App\Services\KeywordFinder;

use App\Models\KeywordApiServer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP wrapper around ONE self-hosted keyword API server. Mirrors the
 * null-tolerant style of {@see \App\Services\KeywordsEverywhereClient}: never
 * throws, logs on failure, lets the caller decide what to do.
 *
 * The dispatch calls ({@see postIdeas}/{@see postVolume}) return a structured
 * outcome so {@see KeywordFinderPool} can tell a transient failure (retry the
 * next server) from a permanent one (give up, surface a friendly error). The
 * server only ACKs here — actual results arrive later via the webhook.
 *
 * Reference endpoints (auth via `x-api-key`):
 *   GET  /health   — liveness, no key required
 *   GET  /status   — { loggedIn: bool, reason?: string }
 *   GET  /queue    — { waiting: int, running: 0|1 }
 *   POST /keywords/ideas    — discovery (seeds or url)
 *   POST /keywords/volume   — metrics for known keywords
 */
class KeywordFinderClient
{
    public function __construct(private KeywordApiServer $server) {}

    /**
     * Detailed GET probe for admin diagnostics: returns the full URL, HTTP
     * status and decoded (or raw) body so the admin page can show exactly what
     * went over the wire.
     *
     * @return array{method: string, url: string, status: ?int, ok: bool, body: mixed, error: ?string}
     */
    public function probe(string $path, bool $auth = true): array
    {
        $url = $this->server->baseUrl().$path;
        try {
            $request = Http::timeout((int) config('services.keyword_finder.request_timeout_s', 15))
                ->connectTimeout(5)
                ->acceptJson();
            if ($auth) {
                $request = $request->withHeaders(['x-api-key' => $this->server->api_key]);
            }

            $response = $request->get($url);
            $json = $response->json();

            return [
                'method' => 'GET',
                'url' => $url,
                'status' => $response->status(),
                'ok' => $response->successful(),
                'body' => is_array($json) ? $json : (string) $response->body(),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'method' => 'GET',
                'url' => $url,
                'status' => null,
                'ok' => false,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /** Liveness — no API key required. Returns decoded body or null. */
    public function health(): ?array
    {
        return $this->get('/health', auth: false);
    }

    /** Login status of the underlying browser session. */
    public function status(): ?array
    {
        return $this->get('/status');
    }

    /** Current queue depth: { waiting, running }. */
    public function queue(): ?array
    {
        return $this->get('/queue');
    }

    /**
     * Dispatch a discovery request. The server ACKs immediately and posts the
     * result to our webhook later.
     *
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: ?int, transient: bool, body: ?array, error: ?string}
     */
    public function postIdeas(array $body): array
    {
        return $this->post('/keywords/ideas', $body);
    }

    /**
     * Dispatch a volume lookup.
     *
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: ?int, transient: bool, body: ?array, error: ?string}
     */
    public function postVolume(array $body): array
    {
        return $this->post('/keywords/volume', $body);
    }

    private function get(string $path, bool $auth = true): ?array
    {
        try {
            $request = Http::timeout((int) config('services.keyword_finder.request_timeout_s', 15))
                ->connectTimeout(5)
                ->acceptJson();

            if ($auth) {
                $request = $request->withHeaders(['x-api-key' => $this->server->api_key]);
            }

            $response = $request->get($this->server->baseUrl().$path);

            if ($response->failed()) {
                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('KeywordFinderClient GET failed', [
                'server_id' => $this->server->id,
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: ?int, transient: bool, body: ?array, error: ?string}
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = Http::timeout((int) config('services.keyword_finder.request_timeout_s', 15))
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['x-api-key' => $this->server->api_key])
                ->asJson()
                ->post($this->server->baseUrl().$path, $body);

            $status = $response->status();
            $json = $response->json();
            $json = is_array($json) ? $json : null;

            if ($response->successful()) {
                return ['ok' => true, 'status' => $status, 'transient' => false, 'body' => $json, 'error' => null];
            }

            // 5xx and 429 are worth trying on another server; 4xx (esp. 400/401/409)
            // are permanent — the request itself or this server's auth is the problem.
            $transient = $status >= 500 || $status === 429;
            $error = is_array($json) && isset($json['error']) && is_string($json['error'])
                ? $json['error']
                : ('HTTP '.$status);

            Log::warning('KeywordFinderClient POST non-2xx', [
                'server_id' => $this->server->id,
                'path' => $path,
                'status' => $status,
                'transient' => $transient,
                'body_snippet' => substr((string) $response->body(), 0, 300),
            ]);

            return ['ok' => false, 'status' => $status, 'transient' => $transient, 'body' => $json, 'error' => $error];
        } catch (\Throwable $e) {
            // Connection refused / timeout / DNS — always transient.
            Log::warning('KeywordFinderClient POST threw', [
                'server_id' => $this->server->id,
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'status' => null, 'transient' => true, 'body' => null, 'error' => $e->getMessage()];
        }
    }
}
