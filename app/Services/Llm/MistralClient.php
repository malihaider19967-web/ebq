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
        $timeout = max(2, min(30, (int) ($options['timeout'] ?? 12)));

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
        if (! ($result['ok'] ?? false)) return null;
        $decoded = json_decode((string) $result['content'], true);
        return is_array($decoded) ? $decoded : null;
    }
}
