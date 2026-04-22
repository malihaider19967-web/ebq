<?php

namespace App\Support;

/**
 * ISO 3166-1 alpha-3 country-code helper.
 *
 * GSC returns country codes as lowercase alpha-3 (e.g. `usa`, `gbr`, `ind`).
 * We normalize them to uppercase on ingest. This class gives UI-facing code
 * a human label for each without requiring the PHP intl extension.
 */
final class Countries
{
    /** @var array<string, string> */
    private const NAMES = [
        'USA' => 'United States', 'GBR' => 'United Kingdom', 'CAN' => 'Canada', 'AUS' => 'Australia',
        'DEU' => 'Germany', 'FRA' => 'France', 'ESP' => 'Spain', 'ITA' => 'Italy',
        'NLD' => 'Netherlands', 'SWE' => 'Sweden', 'NOR' => 'Norway', 'DNK' => 'Denmark',
        'FIN' => 'Finland', 'POL' => 'Poland', 'CHE' => 'Switzerland', 'AUT' => 'Austria',
        'IRL' => 'Ireland', 'BEL' => 'Belgium', 'PRT' => 'Portugal', 'GRC' => 'Greece',
        'CZE' => 'Czechia', 'HUN' => 'Hungary', 'ROU' => 'Romania', 'BGR' => 'Bulgaria',
        'UKR' => 'Ukraine', 'RUS' => 'Russia', 'TUR' => 'Türkiye',
        'IND' => 'India', 'PAK' => 'Pakistan', 'BGD' => 'Bangladesh', 'LKA' => 'Sri Lanka',
        'NPL' => 'Nepal', 'BTN' => 'Bhutan', 'AFG' => 'Afghanistan',
        'ARE' => 'United Arab Emirates', 'SAU' => 'Saudi Arabia', 'QAT' => 'Qatar', 'KWT' => 'Kuwait',
        'BHR' => 'Bahrain', 'OMN' => 'Oman', 'JOR' => 'Jordan', 'LBN' => 'Lebanon',
        'ISR' => 'Israel', 'EGY' => 'Egypt', 'MAR' => 'Morocco', 'TUN' => 'Tunisia',
        'SGP' => 'Singapore', 'MYS' => 'Malaysia', 'IDN' => 'Indonesia', 'THA' => 'Thailand',
        'VNM' => 'Vietnam', 'PHL' => 'Philippines', 'JPN' => 'Japan', 'KOR' => 'South Korea',
        'CHN' => 'China', 'HKG' => 'Hong Kong', 'TWN' => 'Taiwan',
        'BRA' => 'Brazil', 'MEX' => 'Mexico', 'ARG' => 'Argentina', 'CHL' => 'Chile',
        'COL' => 'Colombia', 'PER' => 'Peru', 'URY' => 'Uruguay',
        'ZAF' => 'South Africa', 'NGA' => 'Nigeria', 'KEN' => 'Kenya', 'GHA' => 'Ghana',
        'NZL' => 'New Zealand',
    ];

    public static function name(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }
        $upper = strtoupper(trim($code));

        return self::NAMES[$upper] ?? $upper;
    }

    /**
     * Emoji flag for a country (alpha-3). Falls back to the code itself if
     * we don't have a mapping to alpha-2.
     */
    public static function flag(?string $code): string
    {
        static $a3ToA2 = [
            'USA' => 'US', 'GBR' => 'GB', 'CAN' => 'CA', 'AUS' => 'AU',
            'DEU' => 'DE', 'FRA' => 'FR', 'ESP' => 'ES', 'ITA' => 'IT',
            'NLD' => 'NL', 'SWE' => 'SE', 'NOR' => 'NO', 'DNK' => 'DK',
            'FIN' => 'FI', 'POL' => 'PL', 'CHE' => 'CH', 'AUT' => 'AT',
            'IRL' => 'IE', 'BEL' => 'BE', 'PRT' => 'PT', 'GRC' => 'GR',
            'CZE' => 'CZ', 'HUN' => 'HU', 'ROU' => 'RO', 'BGR' => 'BG',
            'UKR' => 'UA', 'RUS' => 'RU', 'TUR' => 'TR',
            'IND' => 'IN', 'PAK' => 'PK', 'BGD' => 'BD', 'LKA' => 'LK',
            'NPL' => 'NP', 'BTN' => 'BT', 'AFG' => 'AF',
            'ARE' => 'AE', 'SAU' => 'SA', 'QAT' => 'QA', 'KWT' => 'KW',
            'BHR' => 'BH', 'OMN' => 'OM', 'JOR' => 'JO', 'LBN' => 'LB',
            'ISR' => 'IL', 'EGY' => 'EG', 'MAR' => 'MA', 'TUN' => 'TN',
            'SGP' => 'SG', 'MYS' => 'MY', 'IDN' => 'ID', 'THA' => 'TH',
            'VNM' => 'VN', 'PHL' => 'PH', 'JPN' => 'JP', 'KOR' => 'KR',
            'CHN' => 'CN', 'HKG' => 'HK', 'TWN' => 'TW',
            'BRA' => 'BR', 'MEX' => 'MX', 'ARG' => 'AR', 'CHL' => 'CL',
            'COL' => 'CO', 'PER' => 'PE', 'URY' => 'UY',
            'ZAF' => 'ZA', 'NGA' => 'NG', 'KEN' => 'KE', 'GHA' => 'GH',
            'NZL' => 'NZ',
        ];
        if ($code === null || $code === '') {
            return '';
        }
        $upper = strtoupper(trim($code));
        $a2 = $a3ToA2[$upper] ?? null;
        if ($a2 === null) {
            return '';
        }
        $cp = static function (string $letter): string {
            return mb_convert_encoding('&#'.(0x1F1E6 + (ord($letter) - ord('A'))).';', 'UTF-8', 'HTML-ENTITIES');
        };

        return $cp($a2[0]).$cp($a2[1]);
    }
}
