<?php

namespace App\Support\Audit;

/**
 * Resolves Serper/Google-style gl/hl from HTML locale signals (hreflang, og:locale, html lang).
 *
 * @phpstan-type LocaleSignals array{
 *   html_lang: ?string,
 *   og_locale: ?string,
 *   hreflangs: list<array{hreflang: string, href: string}>
 * }
 */
class PageLocaleResolver
{
    /**
     * @param  LocaleSignals  $signals
     * @return array{
     *   signals: LocaleSignals,
     *   gl: ?string,
     *   hl: ?string,
     *   source: ?string,
     *   hreflang_matched: ?string,
     *   bcp47: ?string
     * }
     */
    public static function resolve(array $signals, string $pageUrl): array
    {
        $out = [
            'signals' => $signals,
            'gl' => null,
            'hl' => null,
            'source' => null,
            'hreflang_matched' => null,
            'bcp47' => null,
        ];

        foreach ($signals['hreflangs'] ?? [] as $row) {
            $href = is_string($row['href'] ?? null) ? trim($row['href']) : '';
            $hreflang = is_string($row['hreflang'] ?? null) ? trim($row['hreflang']) : '';
            if ($href === '' || $hreflang === '' || strcasecmp($hreflang, 'x-default') === 0) {
                continue;
            }
            if (! self::urlsFingerprintMatch($href, $pageUrl)) {
                continue;
            }
            $parsed = self::parseLanguageTag($hreflang);
            if ($parsed['hl'] !== null) {
                $out['hl'] = $parsed['hl'];
                $out['gl'] = $parsed['gl'];
                $out['bcp47'] = $parsed['bcp47'];
                $out['source'] = 'hreflang_self';
                $out['hreflang_matched'] = $hreflang;

                return $out;
            }
        }

        $og = isset($signals['og_locale']) ? trim((string) $signals['og_locale']) : '';
        if ($og !== '') {
            $parsed = self::parseLanguageTag(str_replace('_', '-', $og));
            if ($parsed['hl'] !== null) {
                $out['hl'] = $parsed['hl'];
                $out['gl'] = $parsed['gl'];
                $out['bcp47'] = $parsed['bcp47'];
                $out['source'] = 'og_locale';

                return $out;
            }
        }

        $htmlLang = isset($signals['html_lang']) ? trim((string) $signals['html_lang']) : '';
        if ($htmlLang !== '') {
            $parsed = self::parseLanguageTag(str_replace('_', '-', $htmlLang));
            if ($parsed['hl'] !== null) {
                $out['hl'] = $parsed['hl'];
                $out['gl'] = $parsed['gl'];
                $out['bcp47'] = $parsed['bcp47'];
                $out['source'] = 'html_lang';

                return $out;
            }
        }

        return $out;
    }

    /**
     * @return array{hl: ?string, gl: ?string, bcp47: ?string}
     */
    public static function parseLanguageTag(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['hl' => null, 'gl' => null, 'bcp47' => null];
        }

        if (class_exists(\Locale::class)) {
            $canonical = \Locale::canonicalize(str_replace('_', '-', $raw));
            if (is_string($canonical) && $canonical !== '') {
                $hl = \Locale::getPrimaryLanguage($canonical);
                $region = \Locale::getRegion($canonical);
                $hl = $hl !== '' ? strtolower($hl) : null;
                $gl = $region !== '' ? strtolower($region) : null;
                if ($hl !== null) {
                    return [
                        'hl' => $hl,
                        'gl' => $gl,
                        'bcp47' => str_replace('_', '-', $canonical),
                    ];
                }
            }
        }

        $parts = preg_split('/[-_]/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return ['hl' => null, 'gl' => null, 'bcp47' => null];
        }

        $hl = strtolower($parts[0]);
        if (strlen($hl) < 2 || strlen($hl) > 8) {
            return ['hl' => null, 'gl' => null, 'bcp47' => null];
        }

        $gl = null;
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $p = $parts[$i];
            if (strlen($p) === 2 && ctype_alpha($p)) {
                $gl = strtolower($p);
                break;
            }
        }

        $bcp47 = $gl !== null ? $hl.'-'.strtoupper($gl) : $hl;

        return ['hl' => $hl, 'gl' => $gl, 'bcp47' => $bcp47];
    }

    public static function urlsFingerprintMatch(string $a, string $b): bool
    {
        return rtrim(self::urlFingerprint($a), '/') === rtrim(self::urlFingerprint($b), '/');
    }

    private static function urlFingerprint(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts) {
            return strtolower($url);
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '/');

        return $host.$path;
    }
}
