<?php

namespace App\Support\Audit;

class PageLocalePresentation
{
    /**
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

        if ($hl === null && $gl === null) {
            return null;
        }

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

            return $regionName.$suffix;
        }

        if ($langName !== null && $langName !== '') {
            return $hl !== null ? "{$langName} ({$hl})" : $langName;
        }

        if ($gl !== null && $hl !== null) {
            return strtoupper($gl).' · '.$hl;
        }

        return $hl ?? $gl;
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
