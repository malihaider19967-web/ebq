<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\KeywordApiRequest;
use App\Services\KeywordMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives asynchronous results from the self-hosted keyword API fleet. The
 * server POSTs back here with the `request_id` we sent at dispatch time and an
 * HMAC-SHA256 signature of the raw body (keyed on that server's
 * `webhook_secret`).
 *
 * Flow: look up the request → verify the signature against the originating
 * server → store the result (idempotent) → for volume results, upsert the
 * `keyword_metrics` cache. CSRF-exempt (server-to-server) — see bootstrap/app.php.
 */
class KeywordFinderWebhookController extends Controller
{
    public function __invoke(Request $request, KeywordMetricsService $metrics): JsonResponse
    {
        $raw = $request->getContent();
        $data = json_decode($raw, true);

        if (! is_array($data) || empty($data['request_id']) || ! is_string($data['request_id'])) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $apiRequest = KeywordApiRequest::query()
            ->where('request_id', $data['request_id'])
            ->first();

        if ($apiRequest === null) {
            return response()->json(['error' => 'unknown request_id'], 404);
        }

        $server = $apiRequest->server;
        if ($server === null) {
            return response()->json(['error' => 'request has no server'], 409);
        }

        if (! $this->signatureValid($request, $raw, $server->webhook_secret)) {
            Log::warning('KeywordFinder webhook bad signature', [
                'request_id' => $apiRequest->request_id,
                'server_id' => $server->id,
            ]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        // Idempotent: a redelivery for an already-finished request is a no-op.
        if ($apiRequest->isFinished()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // The server may signal a job-level failure two ways: a top-level
        // `status: "failed"` (or "error"), and/or an `error` field that is
        // either a string or an object like { message, needsLogin }.
        $serverStatus = strtolower(trim((string) ($data['status'] ?? '')));
        $errorMessage = $this->extractError($data['error'] ?? null);
        $needsLogin = is_array($data['error'] ?? null) && (($data['error']['needsLogin'] ?? false) === true);

        if (in_array($serverStatus, ['failed', 'error'], true) || $errorMessage !== null) {
            // A re-login requirement is a server-health problem — flag it so the
            // load balancer stops routing here until the next health check.
            if ($needsLogin) {
                $server->forceFill([
                    'is_healthy' => false,
                    'logged_in' => false,
                    'last_error' => 'Browser session needs re-login',
                    'last_health_at' => now(),
                ])->save();
            }

            $apiRequest->markFailed($errorMessage ?? 'The keyword lookup failed on the server.');

            return response()->json(['ok' => true]);
        }

        // Results may be nested under `result`/`data`, or be the body itself.
        $result = $data['result'] ?? $data['data'] ?? $data;
        if (! is_array($result)) {
            $result = [];
        }

        // Cache EVERY returned keyword's volume — for both volume lookups and
        // discovery (ideas) runs. A single "seo audit" discovery can return
        // thousands of related keywords; persisting them all means future
        // searches for any of them are served from cache, free, with no API call.
        $rows = isset($result['results']) && is_array($result['results']) ? $result['results'] : [];
        if ($rows !== []) {
            $countryKey = (string) ($apiRequest->payload['country_key'] ?? 'global');
            $written = $metrics->ingestFinderResults($rows, $countryKey);
            $result['_cached_rows'] = $written;
        }

        $apiRequest->markCompleted($result);

        // A successful callback proves the server is reachable and logged in —
        // clear any stale failure state so the admin page reflects reality.
        if ($server->is_healthy !== true || $server->logged_in !== true || $server->last_error !== null) {
            $server->forceFill([
                'is_healthy' => true,
                'logged_in' => true,
                'last_error' => null,
            ])->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Pull a human-readable message out of the server's `error` field, which
     * may be a plain string or an object like { message, needsLogin }. Returns
     * null when there is no error.
     */
    private function extractError(mixed $error): ?string
    {
        if (is_string($error)) {
            $error = trim($error);

            return $error !== '' ? $error : null;
        }
        if (is_array($error)) {
            $message = $error['message'] ?? $error['error'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
            // An empty array / object means "no error".
            return $error === [] ? null : 'The keyword lookup failed on the server.';
        }

        return null;
    }

    /**
     * Verify HMAC-SHA256 of the raw body. Accepts an optional `sha256=` prefix
     * (common convention). Constant-time comparison.
     */
    private function signatureValid(Request $request, string $raw, string $secret): bool
    {
        $header = (string) config('services.keyword_finder.signature_header', 'x-webhook-signature');
        $provided = trim((string) $request->header($header, ''));
        if ($provided === '' || $secret === '') {
            return false;
        }
        if (str_starts_with($provided, 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $provided);
    }
}
