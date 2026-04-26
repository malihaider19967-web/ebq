<?php

namespace App\Services;

use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered title + meta description rewrites for the live editor.
 *
 * Three rewrites returned per call, each with a one-line rationale so the
 * user can pick the angle (commercial / informational / curiosity-gap).
 * The model sees the focus keyword, the current copy, a body excerpt for
 * intent grounding, and the top-3 SERP titles when available so it can
 * differentiate against what's already ranking.
 *
 * Caching
 * ───────
 * Caches per (post_id × content-hash × focus-keyword × top-3-hash) for 7
 * days. Re-clicks within a week are free; small content edits don't blow
 * the cache (the editor already has plenty of LLM cost in the audit path).
 *
 * MOAT
 * ────
 * Prompt + few-shot examples + competitor SERP grounding all live on EBQ.
 * Plugin only sees rewrites + rationale text. Free-tier uninstall = lose
 * the only "make my snippet better" button in the editor.
 */
class AiSnippetRewriterService
{
    private const CACHE_TTL_DAYS = 7;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   ok: bool,
     *   rewrites?: list<array{title: string, meta: string, rationale: string, angle: string}>,
     *   model?: string,
     *   cached?: bool,
     *   error?: string,
     * }
     */
    public function rewrite(int $postId, array $input): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $focusKeyword = trim((string) ($input['focus_keyword'] ?? ''));
        $currentTitle = trim((string) ($input['current_title'] ?? ''));
        $currentMeta = trim((string) ($input['current_meta'] ?? ''));
        $contentExcerpt = mb_substr(trim((string) ($input['content_excerpt'] ?? '')), 0, 4000);
        $competitorTitles = is_array($input['competitor_titles'] ?? null) ? $input['competitor_titles'] : [];
        $competitorTitles = array_slice(array_values(array_filter(array_map('strval', $competitorTitles))), 0, 3);

        if ($focusKeyword === '') {
            return ['ok' => false, 'error' => 'missing_focus_keyword'];
        }
        if ($contentExcerpt === '') {
            return ['ok' => false, 'error' => 'content_too_short'];
        }

        $cacheKey = $this->cacheKey($postId, $focusKeyword, $currentTitle, $currentMeta, $contentExcerpt, $competitorTitles);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }

        $messages = $this->buildPrompt($focusKeyword, $currentTitle, $currentMeta, $contentExcerpt, $competitorTitles);
        $payload = $this->llm->completeJson($messages, [
            'temperature' => 0.7,
            'max_tokens' => 900,
            'json_object' => true,
            'timeout' => 30,
        ]);

        if (! is_array($payload) || ! isset($payload['rewrites']) || ! is_array($payload['rewrites'])) {
            Log::warning('AiSnippetRewriterService: malformed LLM response', [
                'post_id' => $postId,
                'focus_keyword' => $focusKeyword,
            ]);
            return ['ok' => false, 'error' => 'llm_parse_failed'];
        }

        $rewrites = [];
        foreach ($payload['rewrites'] as $r) {
            if (! is_array($r)) continue;
            $title = trim((string) ($r['title'] ?? ''));
            $meta = trim((string) ($r['meta'] ?? ''));
            if ($title === '' || $meta === '') continue;
            $rewrites[] = [
                'title' => mb_substr($title, 0, 90),
                'meta' => mb_substr($meta, 0, 200),
                'rationale' => mb_substr(trim((string) ($r['rationale'] ?? '')), 0, 220),
                'angle' => mb_substr(trim((string) ($r['angle'] ?? 'general')), 0, 32),
            ];
            if (count($rewrites) >= 3) break;
        }

        if ($rewrites === []) {
            return ['ok' => false, 'error' => 'no_rewrites_returned'];
        }

        $result = [
            'ok' => true,
            'rewrites' => $rewrites,
            'model' => 'mistral',
            'cached' => false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, Carbon::now()->addDays(self::CACHE_TTL_DAYS));
        return $result;
    }

    /**
     * @param  list<string>  $competitorTitles
     * @return list<array{role: string, content: string}>
     */
    private function buildPrompt(string $keyword, string $currentTitle, string $currentMeta, string $excerpt, array $competitorTitles): array
    {
        $competitorBlock = $competitorTitles === []
            ? "(no competitor titles available)"
            : implode("\n", array_map(fn ($t, $i) => sprintf('  %d. %s', $i + 1, $t), $competitorTitles, array_keys($competitorTitles)));

        $system = <<<'SYS'
You are a senior SEO copywriter. You produce snappy, click-worthy SEO titles
and meta descriptions that beat what's already ranking for the target query.

Constraints:
- Title: 50–60 characters. Lead with the focus keyword. No clickbait, no
  ALL CAPS, no lying.
- Meta description: 130–155 characters. Reinforce the focus keyword once,
  state the value the user gets, end with an implicit or explicit CTA.
- Differentiate from the competitor titles when given — do not just rephrase.
- Return STRICTLY valid JSON with the schema below. No prose, no markdown.
SYS;

        $user = <<<USER
Focus keyword: "{$keyword}"

Current SEO title: "{$currentTitle}"
Current meta description: "{$currentMeta}"

Top-ranking competitor titles for this query:
{$competitorBlock}

Content excerpt (use only for intent grounding — do not echo verbatim):
---
{$excerpt}
---

Return JSON exactly in this shape:
{
  "rewrites": [
    {
      "angle": "commercial" | "informational" | "curiosity" | "comparison" | "guide",
      "title": "...",
      "meta": "...",
      "rationale": "Why this angle works against the SERP, in one sentence."
    },
    ... (3 entries total, each a different angle)
  ]
}
USER;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @param  list<string>  $competitorTitles
     */
    private function cacheKey(int $postId, string $keyword, string $title, string $meta, string $excerpt, array $competitorTitles): string
    {
        $contentHash = hash('xxh3', $title . "\n" . $meta . "\n" . $excerpt);
        $compHash = hash('xxh3', implode('|', $competitorTitles));
        return sprintf('ai_snippet_rewrite:%d:%s:%s:%s', $postId, hash('xxh3', $keyword), $contentHash, $compHash);
    }
}
