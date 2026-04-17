<?php

namespace App\Support\Audit;

/**
 * Fills missing Serper/Google {@code gl} when {@code hl} is known so SERP samples use a real country.
 * HTML-derived {@code page_locale} stays unchanged; this applies only to the Serper request payload.
 */
class SerpLocaleDefaults
{
    /** @var array<string, string> Primary BCP47 language → default ISO 3166-1 alpha-2 {@code gl} */
    private const LANGUAGE_DEFAULT_GL = [
        'ar' => 'sa',
        'bg' => 'bg',
        'bn' => 'bd',
        'cs' => 'cz',
        'da' => 'dk',
        'de' => 'de',
        'el' => 'gr',
        'en' => 'us',
        'es' => 'es',
        'et' => 'ee',
        'fa' => 'ir',
        'fi' => 'fi',
        'fil' => 'ph',
        'fr' => 'fr',
        'he' => 'il',
        'hi' => 'in',
        'hr' => 'hr',
        'hu' => 'hu',
        'id' => 'id',
        'is' => 'is',
        'it' => 'it',
        'ja' => 'jp',
        'kn' => 'in',
        'ko' => 'kr',
        'lt' => 'lt',
        'lv' => 'lv',
        'ml' => 'in',
        'ms' => 'my',
        'nl' => 'nl',
        'no' => 'no',
        'pl' => 'pl',
        'pt' => 'br',
        'ro' => 'ro',
        'ru' => 'ru',
        'sk' => 'sk',
        'sl' => 'si',
        'sv' => 'se',
        'sw' => 'ke',
        'ta' => 'in',
        'te' => 'in',
        'th' => 'th',
        'tl' => 'ph',
        'tr' => 'tr',
        'uk' => 'ua',
        'ur' => 'pk',
        'vi' => 'vn',
        'zh' => 'cn',
    ];

    /**
     * @return array{gl: ?string, hl: ?string} Normalized values safe for {@see SerperSearchClient::search}
     */
    public static function forSerperRequest(?string $resolvedGl, ?string $resolvedHl, ?string $bcp47 = null): array
    {
        $hl = self::normalizeHl($resolvedHl);
        $gl = self::normalizeGl($resolvedGl);

        if ($gl === null) {
            $gl = self::regionFromHl($hl ?? '');
            if ($gl === null && is_string($bcp47) && $bcp47 !== '') {
                $gl = self::regionFromBcp47($bcp47);
            }
            if ($gl === null) {
                $gl = self::languageFallbackGl($hl, $bcp47);
            }
        }

        return ['gl' => $gl, 'hl' => $hl];
    }

    public static function isValidSerperGl(?string $gl): bool
    {
        return self::normalizeGl($gl) !== null;
    }

    private static function normalizeGl(?string $gl): ?string
    {
        $t = is_string($gl) ? strtolower(trim($gl)) : '';

        return ($t !== '' && strlen($t) === 2 && ctype_alpha($t)) ? $t : null;
    }

    private static function normalizeHl(?string $hl): ?string
    {
        $t = is_string($hl) ? strtolower(trim($hl)) : '';
        if ($t === '' || preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', $t) !== 1) {
            return null;
        }

        return $t;
    }

    /**
     * Last 2-letter alphabetic subtag after the language (e.g. {@code en-gb} → {@code gb}, {@code zh-hans-cn} → {@code cn}).
     */
    private static function regionFromHl(string $hl): ?string
    {
        $hl = strtolower(trim($hl));
        if ($hl === '') {
            return null;
        }
        $parts = explode('-', $hl);
        if (count($parts) < 2) {
            return null;
        }
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $p = $parts[$i];
            if (strlen($p) === 2 && ctype_alpha($p)) {
                return strtolower($p);
            }
        }

        return null;
    }

    private static function regionFromBcp47(string $bcp47): ?string
    {
        $norm = str_replace('_', '-', $bcp47);
        if (preg_match('/[-_](tw|hk|mo|cn|sg)\b/i', $norm, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    private static function languageFallbackGl(?string $hl, ?string $bcp47): ?string
    {
        $primary = self::primaryLanguage($hl);
        if ($primary === null || $primary === '') {
            return null;
        }

        if ($primary === 'zh') {
            $blob = strtolower(trim(($hl ?? '').' '.($bcp47 ?? '')));

            if (str_contains($blob, 'hant')
                || preg_match('/[-_]tw\b/i', $blob) === 1
                || preg_match('/[-_]hk\b/i', $blob) === 1
                || preg_match('/[-_]mo\b/i', $blob) === 1) {
                return 'tw';
            }

            return 'cn';
        }

        return self::LANGUAGE_DEFAULT_GL[$primary] ?? null;
    }

    private static function primaryLanguage(?string $hl): ?string
    {
        if ($hl === null || $hl === '') {
            return null;
        }
        $hl = strtolower(trim($hl));
        $parts = explode('-', $hl, 2);

        return $parts[0] !== '' ? $parts[0] : null;
    }
}
