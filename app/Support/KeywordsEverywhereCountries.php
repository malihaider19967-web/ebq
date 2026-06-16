<?php

namespace App\Support;

/**
 * The country set supported by the Keywords Everywhere `get_keyword_data`
 * endpoint. KE does NOT accept arbitrary ISO codes — only this short list
 * (plus "global"). Both the portal finder and the public freemium tool
 * validate user input against this so we never burn a credit on a country
 * KE will reject.
 *
 * @see \App\Services\KeywordsEverywhereClient
 */
class KeywordsEverywhereCountries
{
    /** code => label, in display order (global first). */
    private const MAP = [
        'global' => 'Global',
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'ca' => 'Canada',
        'au' => 'Australia',
        'in' => 'India',
        'nz' => 'New Zealand',
        'za' => 'South Africa',
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::MAP;
    }

    public static function isValid(string $code): bool
    {
        return array_key_exists(strtolower(trim($code)), self::MAP);
    }

    /** Normalize to a supported code, falling back to "global". */
    public static function normalize(?string $code): string
    {
        $c = strtolower(trim((string) $code));

        return array_key_exists($c, self::MAP) ? $c : 'global';
    }

    public static function label(string $code): string
    {
        return self::MAP[strtolower(trim($code))] ?? strtoupper($code);
    }
}
