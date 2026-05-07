<?php

namespace App\Services\Research\Niche;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mistral implementation of EmbeddingProvider. Bound by AppServiceProvider
 * only when env('RESEARCH_EMBEDDINGS_ENABLED', false) is truthy AND the
 * Mistral API key is set. Returns empty arrays on transport failure so the
 * caller can fall back to the rule-based path without crashing.
 */
class MistralEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'mistral-embed',
        private readonly string $baseUrl = 'https://api.mistral.ai',
    ) {}

    public function isAvailable(): bool
    {
        if (trim($this->apiKey) === '') {
            return false;
        }

        // Admin-flippable kill-switch. /admin/research/settings can flip
        // this off without a redeploy; no API calls happen until it's
        // back on.
        return \App\Support\ResearchEngineSettings::embeddingsEnabled();
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        $clean = array_values(array_filter(array_map(
            fn ($t) => is_string($t) ? trim($t) : '',
            $texts
        )));
        if ($clean === [] || ! $this->isAvailable()) {
            return array_fill(0, count($texts), []);
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(rtrim($this->baseUrl, '/').'/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $clean,
                    'encoding_format' => 'float',
                ]);
        } catch (\Throwable $e) {
            Log::warning('MistralEmbeddingProvider request failed: '.$e->getMessage());

            return array_fill(0, count($texts), []);
        }

        if ($response->failed()) {
            Log::warning('MistralEmbeddingProvider HTTP '.$response->status());

            return array_fill(0, count($texts), []);
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            return array_fill(0, count($texts), []);
        }

        $byIndex = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $idx = (int) ($row['index'] ?? 0);
            $vector = $row['embedding'] ?? [];
            if (is_array($vector)) {
                $byIndex[$idx] = array_values(array_map('floatval', $vector));
            }
        }

        $out = [];
        foreach ($clean as $i => $_) {
            $out[] = $byIndex[$i] ?? [];
        }

        return $out;
    }
}
