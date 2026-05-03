<?php

namespace App\Services;

use App\Models\BrandVoiceProfile;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Brand voice fingerprint extraction + retrieval.
 *
 * Extraction is a one-time cost per upload: send 2–5 samples to the
 * LLM with a structured-extraction prompt, parse the JSON, persist the
 * fingerprint. Retrieval is a cached single-row read used on every AI
 * Studio call (every tool injects the fingerprint into its prompt).
 *
 * Lives entirely server-side. The plugin only sees a redacted summary
 * of the fingerprint (tone + samples_count + a short prose summary)
 * — never the raw `signature_phrases` or `avoid_phrases` lists, since
 * those are part of the prompt-engineering moat.
 */
class BrandVoiceService
{
    private const CACHE_TTL_SECONDS = 86400;        // 24 hours
    private const MAX_SAMPLE_CHARS = 8000;          // per sample, after stripping HTML
    private const EXTRACTION_TIMEOUT_SECONDS = 120;

    public function __construct(private readonly LlmClient $llm)
    {
    }

    public function forWebsite(Website $website): ?BrandVoiceProfile
    {
        $cacheKey = $this->cacheKey($website);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $profile = BrandVoiceProfile::query()
            ->where('website_id', $website->id)
            ->first();

        Cache::put($cacheKey, $profile ?? false, self::CACHE_TTL_SECONDS);

        return $profile;
    }

    /**
     * Extract a fresh fingerprint from raw sample texts. Up to 5
     * samples; each truncated to ~8k characters (the LLM doesn't need
     * more to learn the voice, and longer prompts blow up cost).
     *
     * @param  list<string>  $samples
     */
    public function extract(Website $website, array $samples): ?BrandVoiceProfile
    {
        $samples = $this->cleanSamples($samples);
        if ($samples === []) {
            return null;
        }

        if (! $this->llm->isAvailable()) {
            Log::warning('BrandVoiceService::extract called but LLM not configured');
            return null;
        }

        @set_time_limit(self::EXTRACTION_TIMEOUT_SECONDS + 30);

        $messages = $this->buildExtractionMessages($samples);

        $response = $this->llm->complete($messages, [
            'temperature' => 0.3,
            'max_tokens' => 1500,
            'json_object' => true,
            'timeout' => self::EXTRACTION_TIMEOUT_SECONDS,
        ]);

        if (! is_array($response) || ($response['ok'] ?? false) !== true) {
            Log::warning('BrandVoiceService: LLM extraction failed', [
                'website_id' => $website->id,
                'error' => is_array($response) ? ($response['error'] ?? 'unknown') : 'invalid',
            ]);
            return null;
        }

        $fingerprint = $this->decodeFingerprint((string) ($response['content'] ?? ''));
        if ($fingerprint === null) {
            Log::warning('BrandVoiceService: could not parse fingerprint JSON', ['website_id' => $website->id]);
            return null;
        }

        $excerpt = mb_substr($samples[0], 0, 240);

        $profile = BrandVoiceProfile::query()->updateOrCreate(
            ['website_id' => $website->id],
            [
                'samples_count' => count($samples),
                'fingerprint' => $fingerprint,
                'sample_excerpt' => $excerpt,
                'last_extracted_at' => Carbon::now(),
            ],
        );

        Cache::forget($this->cacheKey($website));

        return $profile->refresh();
    }

    public function clear(Website $website): void
    {
        BrandVoiceProfile::query()->where('website_id', $website->id)->delete();
        Cache::forget($this->cacheKey($website));
    }

    /**
     * Plugin-safe summary — never echoes signature/avoid phrase lists
     * (those are part of the prompt-engineering moat and should not
     * round-trip to the browser).
     *
     * @return array<string, mixed>
     */
    public function summaryForPlugin(?BrandVoiceProfile $profile): array
    {
        if (! $profile) {
            return [
                'configured' => false,
                'samples_count' => 0,
            ];
        }
        $fp = is_array($profile->fingerprint) ? $profile->fingerprint : [];
        return [
            'configured' => true,
            'samples_count' => (int) $profile->samples_count,
            'last_extracted_at' => $profile->last_extracted_at?->toIso8601String(),
            'sample_excerpt' => (string) ($profile->sample_excerpt ?? ''),
            'summary' => (string) ($fp['summary'] ?? ''),
            'tone' => (string) ($fp['tone'] ?? ''),
            'person' => (string) ($fp['person'] ?? ''),
            'avg_sentence_words' => isset($fp['avg_sentence_words']) ? (int) $fp['avg_sentence_words'] : null,
            'formality_score' => isset($fp['formality_score']) ? (int) $fp['formality_score'] : null,
            'vocabulary_band' => (string) ($fp['vocabulary_band'] ?? ''),
        ];
    }

    /* ─────────────────────── internal ─────────────────────── */

    /**
     * @param  list<string>  $samples
     * @return list<string>
     */
    private function cleanSamples(array $samples): array
    {
        $clean = [];
        foreach ($samples as $s) {
            if (! is_string($s)) {
                continue;
            }
            $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($s)));
            if (mb_strlen($text) < 200) {
                continue;
            }
            $clean[] = mb_substr($text, 0, self::MAX_SAMPLE_CHARS);
            if (count($clean) >= 5) {
                break;
            }
        }
        return $clean;
    }

    /**
     * @param  list<string>  $samples
     * @return list<array{role:string, content:string}>
     */
    private function buildExtractionMessages(array $samples): array
    {
        $samplesBlock = '';
        foreach ($samples as $i => $s) {
            $samplesBlock .= "\n--- SAMPLE " . ($i + 1) . " ---\n" . $s . "\n";
        }

        $system = <<<'SYS'
You are an expert editorial-voice analyst. You will read 2–5 sample
posts written by a single brand and extract a structured fingerprint
of its writing voice.

Return ONE JSON object only. No prose, no markdown fences.

Schema:
{
  "tone": string                          // e.g. "warm and authoritative", "punchy and informal"
  "person": "first" | "second" | "third"  // narrative person used most
  "avg_sentence_words": number            // integer 8–35
  "vocabulary_band": string               // e.g. "everyday English", "industry jargon", "academic"
  "formality_score": number               // 0 (very casual) to 100 (very academic)
  "signature_phrases": string[]           // up to 8 — phrases the brand uses repeatedly
  "avoid_phrases": string[]               // up to 12 — phrases ABSENT from the samples that mark generic AI output
                                          // (always include "delve", "leverage", "in today's fast-paced world",
                                          //  "it's important to note", "as an AI", "tapestry of")
  "opening_patterns": string[]            // up to 4 — how paragraphs/articles tend to open
  "closing_patterns": string[]            // up to 4 — how they close
  "hooks_used": string[]                  // up to 6 — rhetorical hooks (question, anecdote, stat, contrarian)
  "summary": string                       // 1-2 sentence prose summary readers can recognise
}

Rules:
- Be specific to the actual text. Do NOT invent generic descriptions.
- "signature_phrases" must be quotes (≤6 words) actually present in samples.
- Empty arrays are fine if you don't see strong patterns.
SYS;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Extract the brand voice fingerprint from these samples:\n" . $samplesBlock],
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeFingerprint(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'tone' => (string) ($decoded['tone'] ?? ''),
            'person' => in_array(($decoded['person'] ?? ''), ['first', 'second', 'third'], true) ? $decoded['person'] : 'third',
            'avg_sentence_words' => max(5, min(60, (int) ($decoded['avg_sentence_words'] ?? 18))),
            'vocabulary_band' => (string) ($decoded['vocabulary_band'] ?? ''),
            'formality_score' => max(0, min(100, (int) ($decoded['formality_score'] ?? 50))),
            'signature_phrases' => $this->strList($decoded['signature_phrases'] ?? null, 8),
            'avoid_phrases' => $this->strList($decoded['avoid_phrases'] ?? null, 12),
            'opening_patterns' => $this->strList($decoded['opening_patterns'] ?? null, 4),
            'closing_patterns' => $this->strList($decoded['closing_patterns'] ?? null, 4),
            'hooks_used' => $this->strList($decoded['hooks_used'] ?? null, 6),
            'summary' => (string) ($decoded['summary'] ?? ''),
        ];
    }

    /** @return list<string> */
    private function strList(mixed $value, int $max): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $value,
        ), static fn ($v) => $v !== ''));
        return array_slice($out, 0, $max);
    }

    private function cacheKey(Website $website): string
    {
        return 'brand_voice:v1:' . $website->id;
    }
}
