<?php

namespace App\Support\Audit;

/**
 * Builds a recommended Serper {@code gl} from resolved page locale and a short hint for the audit UI.
 */
class SerpGlCountryPrompt
{
    /**
     * Recommended Google country code for the organic SERP sample (same rules as {@see SerpLocaleDefaults::forSerperRequest}).
     */
    public static function recommendedGl(?string $htmlGl, ?string $hl, ?string $bcp47): string
    {
        $eff = SerpLocaleDefaults::forSerperRequest($htmlGl, $hl, $bcp47);
        $g = $eff['gl'];

        return SerpLocaleDefaults::isValidSerperGl($g) ? strtolower((string) $g) : 'us';
    }

    public static function recommendationHint(string $recommendedGl, ?string $htmlGl, ?string $hl, ?string $bcp47): string
    {
        $rec = strtolower(trim($recommendedGl));
        $label = SerpGlCatalog::labelFor($rec);
        $parts = [];
        if (is_string($hl) && trim($hl) !== '') {
            $parts[] = 'detected language `'.trim($hl).'`';
        }
        if (SerpLocaleDefaults::isValidSerperGl($htmlGl)) {
            $parts[] = 'HTML region `'.strtolower(trim((string) $htmlGl)).'`';
        }
        if (is_string($bcp47) && trim($bcp47) !== '') {
            $parts[] = 'locale tag `'.trim($bcp47).'`';
        }
        $ctx = $parts !== [] ? ' based on '.implode(', ', $parts) : ' based on this page';

        return 'Suggested Google SERP country: '.$label.' (`gl='.$rec.'`)'.$ctx.'. Change the selection if you want a different market.';
    }
}
