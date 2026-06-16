<?php

namespace App\Support;

/**
 * Location/language resolution for the self-hosted keyword API. That API wants
 * the EXACT names Google Ads uses ("United States", "Spain", "English"), and
 * "All" / "Global" / "Worldwide" to drop geo-targeting.
 *
 * Internally the app keys its keyword_metrics cache on the Keywords Everywhere
 * short codes ({@see KeywordsEverywhereCountries}). This class bridges the two:
 * a cache country-key in, a Google-Ads location name out.
 *
 * For discovery (ideas) the user may pass any country/region name the picker
 * accepts, so unknown strings pass through untouched rather than being rejected.
 */
class KeywordFinderLocations
{
    /** KE short code => Google Ads country name (legacy aliases). */
    private const CODE_TO_LOCATION = [
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'ca' => 'Canada',
        'au' => 'Australia',
        'in' => 'India',
        'nz' => 'New Zealand',
        'za' => 'South Africa',
    ];

    /**
     * Full country list the self-hosted Keyword Planner targets: cache key
     * (ISO-3166 alpha-2, except 'uk' to stay aligned with the legacy codes the
     * rest of the app uses) => Google Ads country name. Sanctioned locations
     * (Cuba, Iran, North Korea, Syria) are intentionally omitted — Google Ads
     * can't target them. Keys stay ≤16 chars to fit keyword_metrics.country.
     */
    public const COUNTRIES = [
        'us' => 'United States', 'uk' => 'United Kingdom', 'ca' => 'Canada',
        'au' => 'Australia', 'in' => 'India', 'nz' => 'New Zealand', 'za' => 'South Africa',
        'ie' => 'Ireland', 'sg' => 'Singapore', 'ae' => 'United Arab Emirates',
        'sa' => 'Saudi Arabia', 'qa' => 'Qatar', 'kw' => 'Kuwait', 'bh' => 'Bahrain',
        'om' => 'Oman', 'jo' => 'Jordan', 'lb' => 'Lebanon', 'eg' => 'Egypt',
        'ma' => 'Morocco', 'dz' => 'Algeria', 'tn' => 'Tunisia', 'ng' => 'Nigeria',
        'ke' => 'Kenya', 'gh' => 'Ghana', 'de' => 'Germany', 'fr' => 'France',
        'es' => 'Spain', 'it' => 'Italy', 'pt' => 'Portugal', 'nl' => 'Netherlands',
        'be' => 'Belgium', 'ch' => 'Switzerland', 'at' => 'Austria', 'se' => 'Sweden',
        'no' => 'Norway', 'dk' => 'Denmark', 'fi' => 'Finland', 'pl' => 'Poland',
        'cz' => 'Czechia', 'sk' => 'Slovakia', 'hu' => 'Hungary', 'ro' => 'Romania',
        'bg' => 'Bulgaria', 'gr' => 'Greece', 'hr' => 'Croatia', 'si' => 'Slovenia',
        'rs' => 'Serbia', 'ua' => 'Ukraine', 'ee' => 'Estonia', 'lv' => 'Latvia',
        'lt' => 'Lithuania', 'is' => 'Iceland', 'tr' => 'Turkey', 'il' => 'Israel',
        'ru' => 'Russia', 'br' => 'Brazil', 'mx' => 'Mexico', 'ar' => 'Argentina',
        'cl' => 'Chile', 'co' => 'Colombia', 'pe' => 'Peru', 'uy' => 'Uruguay',
        'ec' => 'Ecuador', 'bo' => 'Bolivia', 'py' => 'Paraguay', 've' => 'Venezuela',
        'cr' => 'Costa Rica', 'pa' => 'Panama', 'gt' => 'Guatemala', 'do' => 'Dominican Republic',
        'jp' => 'Japan', 'kr' => 'South Korea', 'cn' => 'China', 'hk' => 'Hong Kong',
        'tw' => 'Taiwan', 'id' => 'Indonesia', 'my' => 'Malaysia', 'th' => 'Thailand',
        'ph' => 'Philippines', 'vn' => 'Vietnam', 'pk' => 'Pakistan', 'bd' => 'Bangladesh',
        'lk' => 'Sri Lanka', 'np' => 'Nepal',
    ];

    /** Language names the API accepts (from the service docs). */
    public const LANGUAGES = [
        'Arabic', 'Bengali', 'Bulgarian', 'Catalan', 'Chinese (simplified)',
        'Chinese (traditional)', 'Croatian', 'Czech', 'Danish', 'Dutch',
        'English', 'Estonian', 'Filipino', 'Finnish', 'French', 'German',
        'Greek', 'Gujarati', 'Hebrew', 'Hindi', 'Hungarian', 'Icelandic',
        'Indonesian', 'Italian', 'Japanese', 'Kannada', 'Korean', 'Latvian',
        'Lithuanian', 'Malay', 'Malayalam', 'Marathi', 'Norwegian', 'Persian',
        'Polish', 'Portuguese', 'Punjabi', 'Romanian', 'Russian', 'Serbian',
        'Slovak', 'Slovenian', 'Spanish', 'Swedish', 'Tamil', 'Telugu', 'Thai',
        'Turkish', 'Ukrainian', 'Urdu', 'Vietnamese',
    ];

    /**
     * Turn an internal country key (or a free-form location name) into the
     * location string the API expects. `global`/`worldwide`/empty → "All".
     */
    public static function resolveLocation(?string $countryKeyOrName): string
    {
        $raw = trim((string) $countryKeyOrName);
        if ($raw === '') {
            return (string) config('services.keyword_finder.default_location', 'United States');
        }

        $lower = strtolower($raw);
        if (in_array($lower, ['global', 'all', 'worldwide'], true)) {
            return 'All';
        }
        if (isset(self::COUNTRIES[$lower])) {
            return self::COUNTRIES[$lower];
        }
        if (isset(self::CODE_TO_LOCATION[$lower])) {
            return self::CODE_TO_LOCATION[$lower];
        }

        // Already a full country/region name (discovery flows) — pass through.
        return $raw;
    }

    /**
     * Country options for a <select>: 'global' first, then every supported
     * country sorted by display name. value=cache key, label=country name.
     *
     * @return array<string, string>
     */
    public static function countryOptions(): array
    {
        $countries = self::COUNTRIES;
        asort($countries);

        return ['global' => 'All countries (Worldwide)'] + $countries;
    }

    /**
     * Location autocomplete suggestions for a free-text <input list>. Per the
     * service docs the picker accepts any Google Ads country name (and even
     * regions/cities), so this is a convenience list, not a hard constraint.
     *
     * @return list<string>
     */
    public static function locationNames(): array
    {
        $names = array_values(self::COUNTRIES);
        sort($names);

        return array_merge(['All'], $names);
    }

    /**
     * Deterministic, ≤16-char cache key for a free-text location, so the volume
     * finder's cache-first lookup and the webhook ingest agree on where to
     * store/read a keyword's volume. Known country names reuse their short code
     * (e.g. "United States" → "us") so the cache stays aligned with the rest of
     * the app; anything else (regions/cities) becomes a slug.
     */
    public static function cacheKey(string $location): string
    {
        $raw = strtolower(trim($location));
        if ($raw === '' || in_array($raw, ['all', 'global', 'worldwide'], true)) {
            return 'global';
        }
        foreach (self::COUNTRIES as $code => $name) {
            if (strcasecmp($name, $location) === 0) {
                return $code;
            }
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $raw), '-');

        return substr($slug !== '' ? $slug : 'global', 0, 16);
    }

    /**
     * Map an internal KE country key to a 2-letter Serper `gl` code. Serper's
     * client only keeps a `gl` that is exactly two alpha chars, so 'global'
     * (and other long keys) would be silently dropped, and 'uk' must become
     * 'gb'. Everything geo-less defaults to the US SERP.
     */
    public static function serperGl(?string $countryKey): string
    {
        $raw = strtolower(trim((string) $countryKey));
        if ($raw === '' || in_array($raw, ['global', 'all', 'worldwide'], true)) {
            return 'us';
        }
        if ($raw === 'uk') {
            return 'gb';
        }
        if (strlen($raw) === 2 && ctype_alpha($raw)) {
            return $raw;
        }

        return 'us';
    }

    /** Resolve a language name, defaulting to the configured default. */
    public static function resolveLanguage(?string $language): string
    {
        $raw = trim((string) $language);
        if ($raw === '') {
            return (string) config('services.keyword_finder.default_language', 'English');
        }

        foreach (self::LANGUAGES as $known) {
            if (strcasecmp($known, $raw) === 0) {
                return $known;
            }
        }

        return $raw;
    }

    /** Languages as value=>label for a <select>. */
    public static function languageOptions(): array
    {
        return array_combine(self::LANGUAGES, self::LANGUAGES);
    }
}
