<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mistral chat-completions client. Defaults to mistral-small-latest
 * (currently Mistral Small 3.2) — cheap, fast, EU-hosted, native JSON
 * output mode. Used by `TopicalGapService` and any other extraction-style
 * EBQ feature.
 *
 * Failure mode is "return ok:false" — never throw from here so the call
 * sites can fall back to non-LLM behavior without crashing the editor.
 */
final class MistralClient implements LlmClient
{
    private const ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';
    private const DEFAULT_MODEL = 'mistral-small-latest';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $defaultModel = self::DEFAULT_MODEL,
    ) {}

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function complete(array $messages, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return [
                'ok' => false,
                'content' => '',
                'model' => '',
                'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0],
                'error' => 'mistral_api_key_missing',
            ];
        }

        $model = (string) ($options['model'] ?? $this->defaultModel);
        // Ceiling raised from 60→300s. JSON-mode requests are slower than
        // free-text (token-by-token grammar checking), and large-output
        // tasks like the AI Writer (up to 20 sections, 16k output tokens)
        // can run 2–4 minutes wall time. Floor stays at 2s; nobody calls
        // intentionally short. Per-call sites pass their own `timeout`,
        // so this is just a safety ceiling.
        $timeout = max(2, min(300, (int) ($options['timeout'] ?? 12)));

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? 0.2),
        ];
        if (! empty($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }
        if (! empty($options['json_object'])) {
            // Mistral mirrors OpenAI's response_format contract.
            $body['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry(2, 250, throw: false)
                ->post(self::ENDPOINT, $body);
        } catch (\Throwable $e) {
            Log::warning('Mistral request threw', ['msg' => $e->getMessage()]);
            return [
                'ok' => false,
                'content' => '',
                'model' => $model,
                'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0],
                'error' => 'mistral_network_error',
            ];
        }

        if (! $response->successful()) {
            Log::warning('Mistral non-2xx', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);
            return [
                'ok' => false,
                'content' => '',
                'model' => $model,
                'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0],
                'error' => 'mistral_http_' . $response->status(),
            ];
        }

        $json = (array) $response->json();
        $choice = $json['choices'][0]['message']['content'] ?? '';
        $usage = (array) ($json['usage'] ?? []);

        return [
            'ok' => true,
            'content' => (string) $choice,
            'model' => (string) ($json['model'] ?? $model),
            'usage' => [
                'prompt' => (int) ($usage['prompt_tokens'] ?? 0),
                'completion' => (int) ($usage['completion_tokens'] ?? 0),
                'total' => (int) ($usage['total_tokens'] ?? 0),
            ],
        ];
    }

    public function completeJson(array $messages, array $options = []): ?array
    {
        $options['json_object'] = true;
        $result = $this->complete($messages, $options);
        if (! ($result['ok'] ?? false)) {
            Log::warning('Mistral completeJson: complete() returned ok=false', [
                'error' => $result['error'] ?? 'unknown',
                'model' => $result['model'] ?? '',
            ]);
            return null;
        }

        $raw = (string) $result['content'];
        $decoded = $this->tolerantJsonDecode($raw);
        if ($decoded === null) {
            Log::warning('Mistral completeJson: parse failed', [
                'model' => $result['model'] ?? '',
                'raw_preview' => mb_substr($raw, 0, 800),
                'raw_length' => mb_strlen($raw),
                'usage' => $result['usage'] ?? null,
            ]);
        }
        return $decoded;
    }

    /**
     * Tolerates the four ways an LLM-in-JSON-mode can still return text
     * that the standard `json_decode` chokes on:
     *
     *   1. Markdown code fences: ```json\n{...}\n```
     *   2. Leading/trailing commentary ("Sure, here's the JSON: {...}")
     *   3. Bare object embedded in a longer string
     *   4. Trailing commas (not strict JSON but common in LLM output)
     *
     * Strategy: try strict decode → strip code fences → extract first
     * balanced `{...}` block → strip trailing commas → strict decode again.
     * Returns null only when no recoverable JSON object can be found.
     */
    private function tolerantJsonDecode(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') return null;

        // (1) Strict decode happy path.
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) return $decoded;

        // (2) Strip markdown code fences (```json … ``` or ``` … ```).
        $stripped = preg_replace('/^```(?:json|JSON)?\s*\n?(.+?)\n?```$/sm', '$1', $trimmed);
        if (is_string($stripped) && $stripped !== $trimmed) {
            $decoded = json_decode(trim($stripped), true);
            if (is_array($decoded)) return $decoded;
            $trimmed = trim($stripped);
        }

        // (3) Extract the first balanced { ... } block (handles leading
        //     commentary like "Here you go: { ... }").
        $start = strpos($trimmed, '{');
        if ($start === false) return null;
        $depth = 0;
        $inString = false;
        $escape = false;
        $end = -1;
        $len = strlen($trimmed);
        for ($i = $start; $i < $len; $i++) {
            $ch = $trimmed[$i];
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\' && $inString) { $escape = true; continue; }
            if ($ch === '"') { $inString = ! $inString; continue; }
            if ($inString) continue;
            if ($ch === '{') $depth++;
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) { $end = $i; break; }
            }
        }
        if ($end === -1) return null;
        $candidate = substr($trimmed, $start, $end - $start + 1);

        // (4) Strip trailing commas before } or ] — common LLM artifact.
        $candidate = preg_replace('/,(\s*[\]}])/', '$1', $candidate) ?? $candidate;

        $decoded = json_decode($candidate, true);
        return is_array($decoded) ? $decoded : null;
    }
}
