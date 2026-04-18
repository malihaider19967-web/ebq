<?php

namespace App\Support\Audit;

/**
 * ISO 3166-1 alpha-2 {@code gl} labels for Serper country pickers (data from ISO-3166-Countries-with-Regional-Codes slim-2).
 */
class SerpGlCatalog
{
    /** @var array<string, string>|null */
    private static ?array $codeToLabel = null;

    /**
     * @return array<string, string> lowercase gl => English country/territory name, sorted by label
     */
    public static function selectOptions(): array
    {
        if (self::$codeToLabel !== null) {
            return self::$codeToLabel;
        }

        $path = __DIR__.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'iso3166-slim-2.json';
        $raw = is_readable($path) ? file_get_contents($path) : false;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $out = [];
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = isset($row['alpha-2']) ? strtolower(trim((string) $row['alpha-2'])) : '';
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($code === '' || strlen($code) !== 2 || ! ctype_alpha($code) || $name === '') {
                    continue;
                }
                $out[$code] = $name;
            }
        }
        if ($out === []) {
            $out = [
                'us' => 'United States',
                'gb' => 'United Kingdom',
                'ca' => 'Canada',
                'au' => 'Australia',
                'de' => 'Germany',
                'fr' => 'France',
                'jp' => 'Japan',
                'in' => 'India',
                'br' => 'Brazil',
                'mx' => 'Mexico',
            ];
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        self::$codeToLabel = $out;

        return self::$codeToLabel;
    }

    public static function labelFor(string $gl): string
    {
        $g = strtolower(trim($gl));
        $opts = self::selectOptions();

        return $opts[$g] ?? strtoupper($g);
    }

    public static function isAllowedGl(string $code): bool
    {
        return SerpLocaleDefaults::isValidSerperGl($code);
    }
}
