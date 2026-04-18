<?php

namespace App\Support\Audit;

/**
 * English pages often omit a region in HTML; Serper needs {@code gl}. Prompt the user instead of guessing.
 */
class SerpEnglishGlSelector
{
    /** @var array<string, string> ISO 3166-1 alpha-2 (lowercase) => English label */
    private const CHOICES = [
        'us' => 'United States',
        'gb' => 'United Kingdom',
        'ca' => 'Canada',
        'au' => 'Australia',
        'nz' => 'New Zealand',
        'ie' => 'Ireland',
        'za' => 'South Africa',
        'in' => 'India',
        'sg' => 'Singapore',
        'my' => 'Malaysia',
        'ph' => 'Philippines',
        'pk' => 'Pakistan',
        'ng' => 'Nigeria',
        'ke' => 'Kenya',
        'ae' => 'United Arab Emirates',
        'sa' => 'Saudi Arabia',
        'hk' => 'Hong Kong',
    ];

    /**
     * @return array<string, string> gl code => label (stable order)
     */
    public static function selectOptions(): array
    {
        return self::CHOICES;
    }

    public static function isAllowedGl(string $code): bool
    {
        $c = strtolower(trim($code));

        return $c !== '' && isset(self::CHOICES[$c]);
    }

    /**
     * Ask for a country when the page language is English and HTML did not supply a Google {@code gl}.
     */
    public static function needsEnglishSerpCountryChoice(?string $hl, ?string $htmlGl): bool
    {
        if (self::primaryHlLanguage($hl) !== 'en') {
            return false;
        }

        return ! SerpLocaleDefaults::isValidSerperGl($htmlGl);
    }

    private static function primaryHlLanguage(?string $hl): ?string
    {
        if ($hl === null) {
            return null;
        }
        $hl = strtolower(trim($hl));
        if ($hl === '') {
            return null;
        }
        $parts = explode('-', $hl, 2);

        return $parts[0] !== '' ? $parts[0] : null;
    }
}
