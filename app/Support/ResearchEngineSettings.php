<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Single read/write point for every runtime-tunable Research-engine
 * knob. Each accessor reads from the `settings` table first (admin-set
 * via /admin/research/settings); falls back to config()/env so a fresh
 * deploy without DB rows still uses sensible defaults.
 *
 * Two layers of "off":
 *   enginePaused()              full kill-switch. Nothing the engine
 *                               drives runs (scheduler tick, auto-
 *                               discovery, outlink fan-out, onboarding
 *                               competitor discovery).
 *   autoDiscoveryDisabled()     sub-option. The Serper-API discovery
 *                               (CompetitorDiscoveryService) stops, but
 *                               scheduled scrapes of existing queued
 *                               research_targets keep running and
 *                               outlink-derived enqueues still happen.
 *
 * Env-only fields (pythonPath, cwd) live in config and aren't surfaced
 * for runtime override — they're deploy-time concerns.
 */
class ResearchEngineSettings
{
    public const KEY = 'research_engine';

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $persisted = Setting::get(self::KEY) ?? [];
        if (! is_array($persisted)) {
            $persisted = [];
        }

        return $persisted + self::defaults();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $current = self::all();
        $merged = array_replace($current, $values);
        Setting::set(self::KEY, $merged);
    }

    public static function enginePaused(): bool
    {
        return (bool) (self::all()['engine_paused'] ?? false);
    }

    public static function autoDiscoveryDisabled(): bool
    {
        return (bool) (self::all()['auto_discovery_disabled'] ?? false);
    }

    public static function autoFetchVolume(): bool
    {
        return (bool) (self::all()['auto_fetch_volume']
            ?? (bool) config('research.auto_fetch_volume', false));
    }

    public static function embeddingsEnabled(): bool
    {
        return (bool) (self::all()['embeddings_enabled']
            ?? (bool) config('research.embeddings.enabled', false));
    }

    public static function rolloutMode(): string
    {
        $mode = self::all()['rollout_mode'] ?? config('research.rollout.mode', 'ga');

        return in_array($mode, ['ga', 'cohort', 'admin'], true) ? (string) $mode : 'ga';
    }

    /** @return list<int> */
    public static function rolloutAllowlist(): array
    {
        $raw = self::all()['rollout_allowlist'] ?? config('research.rollout.allowlist', []);
        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw), fn ($n) => $n > 0));
    }

    /** @return array{keyword_lookup:int, serp_fetch:int, llm_call:int, brief:int} */
    public static function quotas(): array
    {
        $persisted = self::all()['quotas'] ?? [];
        if (! is_array($persisted)) {
            $persisted = [];
        }
        $defaults = (array) config('services.research.limits', []);

        return [
            'keyword_lookup' => (int) ($persisted['keyword_lookup'] ?? $defaults['keyword_lookup'] ?? 1000),
            'serp_fetch' => (int) ($persisted['serp_fetch'] ?? $defaults['serp_fetch'] ?? 500),
            'llm_call' => (int) ($persisted['llm_call'] ?? $defaults['llm_call'] ?? 2000),
            'brief' => (int) ($persisted['brief'] ?? $defaults['brief'] ?? 30),
        ];
    }

    public static function quota(string $resource): int
    {
        return self::quotas()[$resource] ?? 0;
    }

    /** @return array{ceiling_total_pages:int, ceiling_external_per_domain:int, ceiling_depth:int, timeout_seconds:int} */
    public static function scraper(): array
    {
        $persisted = self::all()['scraper'] ?? [];
        if (! is_array($persisted)) {
            $persisted = [];
        }

        return [
            'ceiling_total_pages' => (int) ($persisted['ceiling_total_pages'] ?? config('research.scraper.ceiling_total_pages', 5000)),
            'ceiling_external_per_domain' => (int) ($persisted['ceiling_external_per_domain'] ?? config('research.scraper.ceiling_external_per_domain', 25)),
            'ceiling_depth' => (int) ($persisted['ceiling_depth'] ?? config('research.scraper.ceiling_depth', 6)),
            'timeout_seconds' => (int) ($persisted['timeout_seconds'] ?? config('research.scraper.timeout_seconds', 3600)),
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'engine_paused' => false,
            'auto_discovery_disabled' => false,
            'auto_fetch_volume' => (bool) config('research.auto_fetch_volume', false),
            'embeddings_enabled' => (bool) config('research.embeddings.enabled', false),
            'rollout_mode' => (string) config('research.rollout.mode', 'ga'),
            'rollout_allowlist' => (array) config('research.rollout.allowlist', []),
            'quotas' => (array) config('services.research.limits', []),
            'scraper' => [
                'ceiling_total_pages' => (int) config('research.scraper.ceiling_total_pages', 5000),
                'ceiling_external_per_domain' => (int) config('research.scraper.ceiling_external_per_domain', 25),
                'ceiling_depth' => (int) config('research.scraper.ceiling_depth', 6),
                'timeout_seconds' => (int) config('research.scraper.timeout_seconds', 3600),
            ],
        ];
    }

    /**
     * Read-only deploy-time values, surfaced on the settings page so
     * admins can see what's resolved without grepping files.
     *
     * @return array<string, string>
     */
    public static function diagnostics(): array
    {
        return [
            'python_path' => (string) config('research.scraper.python_path'),
            'cwd' => (string) config('research.scraper.cwd'),
        ];
    }
}
