<?php

namespace App\Support\Audit;

class PageLocalePresentation
{
    /**
     * Short market / locale label for tables (HTML signals plus Serper {@code gl} when relevant).
     *
     * @param  array<string, mixed>|null  $pageLocale
     */
    public static function shortLabel(?array $pageLocale): ?string
    {
        if ($pageLocale === null || $pageLocale === []) {
            return null;
        }

        $gl = isset($pageLocale['gl']) && is_string($pageLocale['gl']) && $pageLocale['gl'] !== ''
            ? strtolower($pageLocale['gl'])
            : null;
        $hl = isset($pageLocale['hl']) && is_string($pageLocale['hl']) && $pageLocale['hl'] !== ''
            ? strtolower($pageLocale['hl'])
            : null;

        $base = null;
        if ($hl !== null || $gl !== null) {
            $regionName = null;
            if ($gl !== null && class_exists(\Locale::class)) {
                try {
                    $regionName = \Locale::getDisplayRegion('-'.strtoupper($gl), 'en');
                } catch (\Throwable) {
                    $regionName = null;
                }
            }

            $langName = null;
            if ($hl !== null && class_exists(\Locale::class)) {
                try {
                    $langName = \Locale::getDisplayLanguage($hl, 'en');
                } catch (\Throwable) {
                    $langName = null;
                }
            }

            if ($regionName !== null && $regionName !== '' && $gl !== null) {
                $suffix = $hl !== null && $hl !== '' ? " · {$hl}" : '';
                $base = $regionName.$suffix;
            } elseif ($langName !== null && $langName !== '') {
                $base = $hl !== null ? "{$langName} ({$hl})" : $langName;
            } elseif ($gl !== null && $hl !== null) {
                $base = strtoupper($gl).' · '.$hl;
            } else {
                $base = $hl ?? $gl;
            }
        }

        return self::appendSerpCountryToMarketLabel($base, $pageLocale, $gl);
    }

    /**
     * @param  array<string, mixed>  $pageLocale
     */
    private static function appendSerpCountryToMarketLabel(?string $base, array $pageLocale, ?string $htmlGl): ?string
    {
        $serpGl = self::pickSerpGlForDisplay($pageLocale);
        if ($serpGl === null) {
            return ($base !== null && $base !== '') ? $base : null;
        }

        $userPicked = isset($pageLocale['serp_gl_user_chosen'])
            && is_string($pageLocale['serp_gl_user_chosen'])
            && SerpLocaleDefaults::isValidSerperGl($pageLocale['serp_gl_user_chosen']);

        $showSerp = true;
        if (! $userPicked && $htmlGl !== null && strtolower($htmlGl) === $serpGl) {
            $showSerp = false;
        }

        if (! $showSerp) {
            return ($base !== null && $base !== '') ? $base : null;
        }

        $lab = SerpGlCatalog::labelFor($serpGl);
        $chunk = 'SERP: '.$lab.' ('.$serpGl.')';

        if ($base !== null && $base !== '') {
            return $base.' · '.$chunk;
        }

        return $chunk;
    }

    /**
     * @param  array<string, mixed>  $pageLocale
     */
    private static function pickSerpGlForDisplay(array $pageLocale): ?string
    {
        if (isset($pageLocale['serp_gl_user_chosen'])
            && is_string($pageLocale['serp_gl_user_chosen'])
            && SerpLocaleDefaults::isValidSerperGl($pageLocale['serp_gl_user_chosen'])) {
            return strtolower(trim($pageLocale['serp_gl_user_chosen']));
        }
        if (isset($pageLocale['serp_gl_effective'])
            && is_string($pageLocale['serp_gl_effective'])
            && SerpLocaleDefaults::isValidSerperGl($pageLocale['serp_gl_effective'])) {
            return strtolower(trim($pageLocale['serp_gl_effective']));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $pageLocale
     */
    public static function serpParamsLine(?array $pageLocale): ?string
    {
        if ($pageLocale === null) {
            return null;
        }
        $gl = isset($pageLocale['gl']) && is_string($pageLocale['gl']) && $pageLocale['gl'] !== '' ? $pageLocale['gl'] : null;
        $hl = isset($pageLocale['hl']) && is_string($pageLocale['hl']) && $pageLocale['hl'] !== '' ? $pageLocale['hl'] : null;
        if ($gl === null && $hl === null) {
            return null;
        }
        $parts = [];
        if ($gl !== null) {
            $parts[] = 'gl='.$gl;
        }
        if ($hl !== null) {
            $parts[] = 'hl='.$hl;
        }

        return implode(', ', $parts);
    }

    /**
     * Whether to show the SERP sample region line in audit UI (hidden when Serper `hl` is English).
     *
     * @param  array<string, mixed>|null  $serpLocale  Benchmark {@code serp_locale} as sent to Serper
     */
    public static function shouldShowSerpLocationNote(?array $serpLocale): bool
    {
        if (self::serpParamsLine($serpLocale) === null) {
            return false;
        }

        $hl = isset($serpLocale['hl']) && is_string($serpLocale['hl']) && $serpLocale['hl'] !== ''
            ? strtolower($serpLocale['hl'])
            : '';
        if ($hl === '') {
            return true;
        }

        $primary = explode('-', $hl, 2)[0];

        return $primary !== 'en';
    }
}
