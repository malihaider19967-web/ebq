<?php

namespace App\AiTools\Prompts;

/**
 * Renders a lightweight SEO-state snapshot into a system-prompt
 * fragment. Tools that opt into SIGNAL_SEO_ANALYSIS get this block
 * inserted between the brand-voice and tool-specific addendum, so
 * generated content actively closes the gaps the analysis flagged
 * (missing focus-kw mentions, weak structure, missing entities,
 * out-of-target reading grade, etc.).
 *
 * Returns an empty string when the analysis is null — the writer
 * falls back to brand voice + universal guardrails alone.
 */
final class SeoAnalysisBlock
{
    public static function from(?array $analysis): string
    {
        if (! is_array($analysis) || $analysis === []) {
            return '';
        }

        $lines = ['LIVE SEO ANALYSIS (honour these — produce content that closes the gaps):'];

        $score = $analysis['score'] ?? null;
        if (is_numeric($score)) {
            $lines[] = "- Current SEO score: {$score}/100.";
        }

        $issues = is_array($analysis['issues'] ?? null) ? $analysis['issues'] : [];
        if ($issues !== []) {
            $lines[] = '- Issues to fix in this generation:';
            foreach (array_slice($issues, 0, 6) as $issue) {
                $title = trim((string) ($issue['title'] ?? ''));
                if ($title === '') continue;
                $sev = (string) ($issue['severity'] ?? '');
                $sevTag = $sev !== '' ? '[' . strtoupper($sev) . '] ' : '';
                $lines[] = "    • {$sevTag}{$title}";
            }
        }

        $missing = is_array($analysis['missing_entities'] ?? null) ? $analysis['missing_entities'] : [];
        if ($missing !== []) {
            $lines[] = '- Entities competitors mention that this draft does not — weave them in naturally where they fit:';
            $lines[] = '    ' . implode(', ', array_slice(array_filter($missing, 'is_string'), 0, 8));
        }

        if (isset($analysis['kw_density_pct'], $analysis['kw_density_target'])) {
            $density = (float) $analysis['kw_density_pct'];
            $target = (string) $analysis['kw_density_target'];
            $kw = (string) ($analysis['focus_keyword'] ?? '');
            $lines[] = sprintf(
                '- Focus keyword%s density: %.2f%% (target %s). %s',
                $kw !== '' ? " '{$kw}'" : '',
                $density,
                $target,
                $density === 0.0
                    ? 'Currently absent — include it 2–3 times naturally.'
                    : 'Adjust mentions toward the target without keyword stuffing.',
            );
        }

        if (isset($analysis['reading_grade'], $analysis['reading_grade_target'])) {
            $grade = (float) $analysis['reading_grade'];
            $target = (int) $analysis['reading_grade_target'];
            if ($grade > $target + 0.5) {
                $lines[] = sprintf(
                    '- Reading grade %.0f, target ≤%d — shorten sentences and prefer plain everyday words.',
                    $grade,
                    $target,
                );
            }
        }

        $structure = is_array($analysis['structure'] ?? null) ? $analysis['structure'] : [];
        if (! empty($structure['needs_h1'])) {
            $lines[] = '- The post has no H1 yet — when writing, do not introduce one (the post title field carries the H1); start at H2.';
        }
        if (! empty($structure['flat_sections'])) {
            $lines[] = '- Long sections currently lack H3 subheadings — break dense passages into <h3>-led subsections when generating new content.';
        }
        if (! empty($structure['kw_missing_h1']) && ! empty($structure['has_h1'])) {
            $lines[] = '- The H1 does not contain the focus keyword. When you generate or rewrite intro/headings, work the focus keyword in naturally.';
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }
}
