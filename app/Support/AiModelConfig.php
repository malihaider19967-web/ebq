<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Platform-wide AI model selection. Admin picks which Mistral chat model
 * the LlmClient defaults to; every service that doesn't pass an explicit
 * `model` option in its LLM call picks this up.
 *
 * Persistence: `settings.ai.llm.model` (via App\Models\Setting). Reads
 * fall back to config('services.mistral.model'), then to the
 * built-in default `mistral-small-latest`.
 *
 * Model list: pulled live from Mistral's /v1/models endpoint (cached
 * for an hour so the admin page doesn't burn the rate-limit quota on
 * every load), with a known-good fallback when the API isn't reachable
 * so the dropdown is never empty.
 */
class AiModelConfig
{
    public const SETTING_KEY = 'ai.llm.model';

    /** Mistral's models endpoint. Public — only auth header required. */
    private const MODELS_ENDPOINT = 'https://api.mistral.ai/v1/models';

    /** Cache TTL for the live models list. */
    private const MODELS_CACHE_KEY = 'ai_model_config:available_models:v2';
    private const MODELS_CACHE_TTL_SEC = 3600;

    /**
     * Known-good fallback when the API key is missing or Mistral is
     * unreachable. Keeps the admin dropdown usable rather than empty.
     *
     * Each entry is `[id, human-readable-name]`. We pad this list with
     * both the rolling `*-latest` aliases AND specific dated IDs so
     * admins can pin to a precise model (e.g. "Mistral Small 3.2" =
     * `mistral-small-2506`) without depending on the live API call.
     */
    private const FALLBACK_MODELS = [
        ['mistral-small-latest',  'Mistral Small (latest alias)'],
        ['mistral-small-2506',    'Mistral Small 3.2'],
        ['mistral-small-2503',    'Mistral Small 3.1'],
        ['mistral-small-2501',    'Mistral Small 3'],
        ['mistral-medium-latest', 'Mistral Medium (latest alias)'],
        ['mistral-medium-2508',   'Mistral Medium 3.1'],
        ['mistral-medium-2505',   'Mistral Medium 3'],
        ['mistral-large-latest',  'Mistral Large (latest alias)'],
        ['mistral-large-2411',    'Mistral Large 2.1'],
        ['mistral-large-2407',    'Mistral Large 2'],
        ['ministral-3b-latest',   'Ministral 3B'],
        ['ministral-8b-latest',   'Ministral 8B'],
        ['magistral-medium-latest', 'Magistral Medium (reasoning, latest alias)'],
        ['magistral-small-latest',  'Magistral Small (reasoning, latest alias)'],
        ['codestral-latest',      'Codestral (latest alias)'],
        ['codestral-2508',        'Codestral 25.08'],
        ['pixtral-large-latest',  'Pixtral Large (vision, latest alias)'],
        ['pixtral-12b-2409',      'Pixtral 12B'],
        ['open-mistral-7b',       'Open Mistral 7B'],
        ['open-mixtral-8x7b',     'Open Mixtral 8x7B'],
        ['open-mixtral-8x22b',    'Open Mixtral 8x22B'],
    ];

    /**
     * The model the LlmClient should default to. Resolution order:
     *   1. Admin-selected Setting row
     *   2. config('services.mistral.model') — the .env-driven default
     *   3. 'mistral-small-latest' — last-resort literal
     */
    public static function currentModel(): string
    {
        $stored = Setting::get(self::SETTING_KEY);
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }
        $configured = (string) config('services.mistral.model', '');
        if ($configured !== '') {
            return $configured;
        }
        return 'mistral-small-latest';
    }

    /**
     * Persist the admin's choice. Caller is expected to have already
     * validated $model against listAvailableModels() so we don't
     * accept arbitrary strings here.
     */
    public static function setModel(string $model): void
    {
        Setting::set(self::SETTING_KEY, $model);
    }

    /**
     * List every chat-capable model the configured Mistral key has
     * access to. Cached for an hour. Falls back to the known-good
     * static list when the API isn't reachable so the admin form is
     * never empty.
     *
     * Returned shape: list of ['id' => string, 'label' => string].
     *
     * @return list<array{id:string,label:string}>
     */
    public static function listAvailableModels(): array
    {
        $cached = Cache::get(self::MODELS_CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $apiKey = (string) config('services.mistral.key', '');
        if ($apiKey === '') {
            return self::fallbackList();
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(8)
                ->get(self::MODELS_ENDPOINT);
        } catch (\Throwable $e) {
            Log::warning('AiModelConfig: models endpoint threw', ['msg' => $e->getMessage()]);
            return self::fallbackList();
        }

        if (! $response->successful()) {
            Log::warning('AiModelConfig: models endpoint non-2xx', [
                'status' => $response->status(),
                'body'   => mb_substr((string) $response->body(), 0, 200),
            ]);
            return self::fallbackList();
        }

        $data = (array) ($response->json('data') ?? []);
        $shaped = [];
        $seen = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            // Mistral exposes embedding models, moderation models, and
            // chat models through the same list — filter to chat-style
            // entries by checking the capabilities map when present.
            $caps = is_array($row['capabilities'] ?? null) ? $row['capabilities'] : [];
            if (array_key_exists('completion_chat', $caps) && ! (bool) $caps['completion_chat']) {
                continue;
            }
            $deprecation = (string) ($row['deprecation'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));

            $primary = self::shapeRow($id, $name, $description, $deprecation);
            if (! isset($seen[$primary['id']])) {
                $shaped[] = $primary;
                $seen[$primary['id']] = true;
            }

            // Mistral's models endpoint groups versioned variants under
            // an `aliases` array on the rolling alias row (and vice
            // versa). Expand them as separate dropdown entries so an
            // admin can pin to "Mistral Small 3.2" (id
            // `mistral-small-2506`) instead of being stuck on the
            // moving `mistral-small-latest` target.
            foreach ((array) ($row['aliases'] ?? []) as $aliasId) {
                if (! is_string($aliasId) || $aliasId === '' || isset($seen[$aliasId])) {
                    continue;
                }
                $shaped[] = self::shapeRow($aliasId, $name, $description, $deprecation);
                $seen[$aliasId] = true;
            }
        }

        usort($shaped, static fn (array $a, array $b) => strcmp($a['id'], $b['id']));

        if ($shaped === []) {
            return self::fallbackList();
        }

        Cache::put(self::MODELS_CACHE_KEY, $shaped, self::MODELS_CACHE_TTL_SEC);
        return $shaped;
    }

    /**
     * Compose a `{id, label}` row for the dropdown. The label puts the
     * human-readable name first when Mistral provided one, then the
     * raw id in parens, then a short description tail. Deprecated
     * models get a "(deprecated)" suffix so admins don't pin to them.
     *
     * @return array{id:string,label:string}
     */
    private static function shapeRow(string $id, string $name, string $description, string $deprecation): array
    {
        $parts = [];
        if ($name !== '') {
            $parts[] = $name.' ('.$id.')';
        } else {
            $parts[] = $id;
        }
        if ($description !== '') {
            $parts[] = mb_substr($description, 0, 80);
        }
        if ($deprecation !== '') {
            $parts[] = '⚠ deprecated';
        }
        return [
            'id'    => $id,
            'label' => implode(' — ', $parts),
        ];
    }

    /** Clear the cached models list — call after admin changes the API key. */
    public static function clearModelsCache(): void
    {
        Cache::forget(self::MODELS_CACHE_KEY);
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private static function fallbackList(): array
    {
        return array_map(
            static fn (array $row) => self::shapeRow($row[0], $row[1], '', ''),
            self::FALLBACK_MODELS,
        );
    }
}
