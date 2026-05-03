<?php

namespace App\AiTools\Prompts;

use App\Models\BrandVoiceProfile;

/**
 * Renders a brand-voice fingerprint into a system-prompt fragment.
 * When no fingerprint exists, returns an empty string — generic AI
 * voice falls back to the universal guardrails' "human voice" rule.
 */
final class BrandVoiceBlock
{
    public static function from(?BrandVoiceProfile $profile): string
    {
        if (! $profile) {
            return '';
        }
        $fp = is_array($profile->fingerprint) ? $profile->fingerprint : [];

        $lines = ['BRAND VOICE (match this voice — it is more important than any default style):'];

        if (! empty($fp['tone']) && is_string($fp['tone'])) {
            $lines[] = '- Tone: ' . trim($fp['tone']);
        }
        if (! empty($fp['person']) && is_string($fp['person'])) {
            $lines[] = '- Person: ' . trim($fp['person']);
        }
        if (! empty($fp['avg_sentence_words']) && is_numeric($fp['avg_sentence_words'])) {
            $lines[] = '- Sentence length: average ~' . (int) $fp['avg_sentence_words'] . ' words; vary naturally.';
        }
        if (! empty($fp['vocabulary_band']) && is_string($fp['vocabulary_band'])) {
            $lines[] = '- Vocabulary: ' . $fp['vocabulary_band'];
        }
        if (! empty($fp['formality_score']) && is_numeric($fp['formality_score'])) {
            $f = (int) $fp['formality_score'];
            $lines[] = '- Formality (0=casual, 100=academic): ' . $f;
        }
        $sigs = self::strList($fp['signature_phrases'] ?? null);
        if ($sigs !== []) {
            $lines[] = '- Use signature phrases naturally where they fit (do not force):';
            foreach (array_slice($sigs, 0, 8) as $s) {
                $lines[] = '    • ' . $s;
            }
        }
        $avoid = self::strList($fp['avoid_phrases'] ?? null);
        if ($avoid !== []) {
            $lines[] = '- Never use these words/phrases:';
            foreach (array_slice($avoid, 0, 12) as $s) {
                $lines[] = '    • ' . $s;
            }
        }
        $opens = self::strList($fp['opening_patterns'] ?? null);
        if ($opens !== []) {
            $lines[] = '- Typical opening patterns (mirror the cadence, not the literal words):';
            foreach (array_slice($opens, 0, 4) as $s) {
                $lines[] = '    • ' . $s;
            }
        }
        $closes = self::strList($fp['closing_patterns'] ?? null);
        if ($closes !== []) {
            $lines[] = '- Typical closing patterns:';
            foreach (array_slice($closes, 0, 4) as $s) {
                $lines[] = '    • ' . $s;
            }
        }
        $hooks = self::strList($fp['hooks_used'] ?? null);
        if ($hooks !== []) {
            $lines[] = '- Hooks the brand uses: ' . implode(', ', array_slice($hooks, 0, 6));
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * @return list<string>
     */
    private static function strList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $value,
        ), static fn ($v) => $v !== ''));
    }
}
