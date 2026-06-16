<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Which provider backs keyword search-volume lookups. Two coexist:
 *
 *   - `keywords_everywhere` — the original synchronous credit-billed API.
 *   - `keyword_finder`      — our self-hosted async fleet (Keyword Planner).
 *
 * Stored in the `Setting` table so an admin can flip it live from the platform
 * settings page. Defaults to Keywords Everywhere so behaviour is unchanged
 * until an admin opts in.
 */
class KeywordProviderConfig
{
    public const PROVIDER_KEYWORDS_EVERYWHERE = 'keywords_everywhere';
    public const PROVIDER_KEYWORD_FINDER = 'keyword_finder';

    public const SETTING_KEY = 'keyword.volume_provider';

    /** @var list<string> */
    public const PROVIDERS = [
        self::PROVIDER_KEYWORDS_EVERYWHERE,
        self::PROVIDER_KEYWORD_FINDER,
    ];

    public static function currentProvider(): string
    {
        $value = (string) Setting::get(self::SETTING_KEY, self::PROVIDER_KEYWORDS_EVERYWHERE);

        return in_array($value, self::PROVIDERS, true)
            ? $value
            : self::PROVIDER_KEYWORDS_EVERYWHERE;
    }

    public static function setProvider(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            $provider = self::PROVIDER_KEYWORDS_EVERYWHERE;
        }
        Setting::set(self::SETTING_KEY, $provider);
    }

    public static function usingKeywordFinder(): bool
    {
        return self::currentProvider() === self::PROVIDER_KEYWORD_FINDER;
    }

    /** @return array<string, string> value => human label, for the admin select. */
    public static function options(): array
    {
        return [
            self::PROVIDER_KEYWORDS_EVERYWHERE => 'Keywords Everywhere (credit-billed)',
            self::PROVIDER_KEYWORD_FINDER => 'Self-hosted Keyword Planner (async)',
        ];
    }
}
