<?php

namespace App\Services;

use App\Models\Website;
use App\Services\Llm\LlmClient;
use App\Services\AiContentBriefService;

/**
 * Block-level AI ops invoked from the Gutenberg block toolbar:
 *   - command:   user supplies an instruction; service generates a block.
 *   - extend:    continue the existing block; the original text is kept at the start.
 *   - summarise: condense the existing block into 2–4 sentences.
 *   - grammar:   correct the existing block without changing meaning.
 *   - rewrite:   recompose the existing block — clearer, tighter, same meaning.
 *
 * Single-shot, no caching, no chained calls — the editor needs sub-15s latency.
 * For full-post drafting use AiWriterService instead.
 */
class AiBlockEditorService
{
    public const MODE_COMMAND = 'command';
    public const MODE_EXTEND = 'extend';
    public const MODE_SUMMARISE = 'summarise';
    public const MODE_GRAMMAR = 'grammar';
    public const MODE_REWRITE = 'rewrite';

    public const MODES = [
        self::MODE_COMMAND,
        self::MODE_EXTEND,
        self::MODE_SUMMARISE,
        self::MODE_GRAMMAR,
        self::MODE_REWRITE,
    ];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly AiContentBriefService $briefService,
    ) {
    }

    /**
     * @param  array{
     *     mode?: string,
     *     text?: string|null,
     *     command?: string|null,
     *     focus_keyword?: string|null,
     *     additional_keywords?: array<int, string>|null,
     *     title?: string|null,
     * }  $input
     * @return array{ok: bool, text?: string, error?: string}
     */
    public function generate(Website $website, array $input): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $mode = (string) ($input['mode'] ?? '');
        if (! in_array($mode, self::MODES, true)) {
            return ['ok' => false, 'error' => 'invalid_mode'];
        }

        $text = trim((string) ($input['text'] ?? ''));
        $command = trim((string) ($input['command'] ?? ''));
        $focusKeyword = trim((string) ($input['focus_keyword'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $additionalKeywords = array_values(array_filter(
            array_map(static fn ($k) => trim((string) $k), (array) ($input['additional_keywords'] ?? [])),
            static fn (string $k): bool => $k !== '',
        ));

        $needsText = in_array($mode, [self::MODE_EXTEND, self::MODE_SUMMARISE, self::MODE_GRAMMAR, self::MODE_REWRITE], true);
        if ($needsText && $text === '') {
            return ['ok' => false, 'error' => 'missing_text'];
        }
        if ($mode === self::MODE_COMMAND && $command === '') {
            return ['ok' => false, 'error' => 'missing_command'];
        }

        // Cache-only lookup — never triggers a fresh brief run. We only
        // enrich when the page already has one cached; otherwise we degrade
        // gracefully to focus-keyword-only context.
        $brief = $mode !== self::MODE_GRAMMAR && $focusKeyword !== ''
            ? ($this->briefService->cachedBrief($website, $focusKeyword) ?? null)
            : null;
        $briefContext = $this->extractBriefContext($brief);

        [$system, $user] = $this->buildPrompt($mode, $text, $command, $focusKeyword, $title, $additionalKeywords, $briefContext);

        $response = $this->llm->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], [
            'temperature' => $this->temperatureFor($mode),
            'max_tokens' => 1500,
            'timeout' => 90,
        ]);

        if (! ($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($response['error'] ?? 'llm_failed')];
        }

        $generated = trim((string) ($response['content'] ?? ''));
        $generated = $this->stripCodeFences($generated);
        if ($generated === '') {
            return ['ok' => false, 'error' => 'llm_empty_response'];
        }

        return ['ok' => true, 'text' => $generated];
    }

    /**
     * Pull the prompt-relevant subset of a cached brief. We deliberately
     * cap each list short — block-level ops produce 50–500 words, so a
     * dozen entities and four subtopics is enough signal without crowding
     * the context window or pulling the model off task.
     *
     * @param  array<string, mixed>|null  $brief
     * @return array{subtopics: list<string>, entities: list<string>, paa: list<string>, recommended_word_count: int, angle: string}|null
     */
    private function extractBriefContext(?array $brief): ?array
    {
        if (! is_array($brief) || ! isset($brief['brief']) || ! is_array($brief['brief'])) {
            return null;
        }
        $b = $brief['brief'];
        $list = static function ($v, int $cap): array {
            if (! is_array($v)) return [];
            $out = [];
            foreach ($v as $item) {
                $s = trim((string) $item);
                if ($s !== '') {
                    $out[] = $s;
                    if (count($out) >= $cap) break;
                }
            }
            return $out;
        };
        return [
            'subtopics' => $list($b['subtopics'] ?? [], 6),
            'entities' => $list($b['must_have_entities'] ?? [], 8),
            'paa' => $list($b['people_also_ask'] ?? [], 4),
            'recommended_word_count' => (int) ($b['recommended_word_count'] ?? 0),
            'angle' => trim((string) ($b['angle'] ?? '')),
        ];
    }

    /**
     * @param  list<string>  $additionalKeywords
     * @param  array{subtopics: list<string>, entities: list<string>, paa: list<string>, recommended_word_count: int, angle: string}|null  $briefContext
     * @return array{0: string, 1: string} [systemPrompt, userPrompt]
     */
    private function buildPrompt(string $mode, string $text, string $command, string $focusKeyword = '', string $title = '', array $additionalKeywords = [], ?array $briefContext = null): array
    {
        // SEO-first system prompt. Every block is part of a page that needs to
        // rank for a target query — the model should think like a search-led
        // content editor, not a generic copywriter. Grammar mode opts out of
        // SEO rewrites because proofing should never change phrasing.
        $system = "You are a senior SEO content editor working inside a WordPress Gutenberg post. The user is creating content meant to rank in Google search and earn organic traffic — every choice you make should serve that goal.\n\n"
            ."When generating or modifying text:\n"
            ."- Match user search intent. If the focus keyword is informational, explain; if commercial, persuade; if navigational, be direct.\n"
            ."- Use the focus keyword and natural semantic variants the way a human writer would — never stuffed, never robotic.\n"
            ."- Add specifics: real numbers, concrete examples, named entities. Generic AI-flavored prose ('in today's fast-paced world…') ranks poorly.\n"
            ."- Keep paragraphs scannable (2–4 sentences). Prefer concrete nouns and active voice.\n"
            ."- Stay on the page's topic. Do not drift into adjacent themes.\n\n"
            ."OUTPUT FORMAT:\n"
            ."- Default to plain prose. Do NOT wrap plain prose in any HTML tags — the editor adds <p> automatically.\n"
            ."- When the user's instruction clearly implies STRUCTURED content, return clean HTML using the appropriate elements:\n"
            ."  · Comparisons → <table> with <thead>/<tbody>/<tr>/<th>/<td>\n"
            ."  · Step-by-step / ordered process → <ol><li>…</li></ol>\n"
            ."  · Feature lists / bullet points → <ul><li>…</li></ul>\n"
            ."  · Multi-section content → <h2>/<h3> for section titles, plain prose between\n"
            ."  · Inline emphasis → <strong>, <em>, <a href=\"…\">\n"
            ."- Never use <html>, <head>, <body>, <div>, <span>, inline style attributes, classes, scripts, or any wrapper containers.\n"
            ."- Never use markdown syntax. Specifically: NO pipe tables (` | col | col | `), NO `-` or `*` bullet lines, NO `#` headings, NO `**bold**`, NO `*italic*`, NO ``` code fences, NO `[text](url)` links. ALWAYS use the equivalent HTML tags: <table>/<tr>/<td>, <ul>/<li>, <ol>/<li>, <h2>, <strong>, <em>, <a href=\"…\">.\n"
            ."- Match the input language.\n"
            ."- Return ONLY the result — no preamble, no explanation.";

        // Build a short SEO context block to prepend to user prompts when we
        // know the page's focus keyword, additional keyphrases, title, or
        // cached audit/brief signals. Skipped on grammar mode (proofreading)
        // because any extra context tempts the model to rephrase.
        $seoContext = '';
        if ($mode !== self::MODE_GRAMMAR) {
            $parts = [];
            if ($title !== '') {
                $parts[] = "Page title: {$title}";
            }
            if ($focusKeyword !== '') {
                $parts[] = "Focus keyword: {$focusKeyword}";
            }
            if (! empty($additionalKeywords)) {
                $parts[] = 'Additional keyphrases (weave naturally where they fit, never forced): '
                    .implode(', ', array_slice($additionalKeywords, 0, 10));
            }
            if ($briefContext !== null) {
                if ($briefContext['angle'] !== '') {
                    $parts[] = "Search intent: {$briefContext['angle']}";
                }
                if ($briefContext['recommended_word_count'] > 0) {
                    $parts[] = "Target full-page word count: {$briefContext['recommended_word_count']} (the block you write is one part of this — write at the right level of depth for that scale)";
                }
                if (! empty($briefContext['subtopics'])) {
                    $parts[] = 'Subtopics top SERP results cover: '.implode(' · ', $briefContext['subtopics']);
                }
                if (! empty($briefContext['entities'])) {
                    $parts[] = 'Entities to mention if relevant: '.implode(', ', $briefContext['entities']);
                }
                if (! empty($briefContext['paa'])) {
                    $parts[] = 'People also ask: '.implode(' | ', $briefContext['paa']);
                }
            }
            if (! empty($parts)) {
                $seoContext = "SEO context for this page (use it to guide vocabulary, search intent, depth, and emphasis — DO NOT mention this context, the keyword list, or the entity list in your output):\n- "
                    .implode("\n- ", $parts)
                    ."\n\n";
            }
        }

        return match ($mode) {
            self::MODE_COMMAND => [
                $system,
                $text !== ''
                    ? $seoContext."Apply the user's instruction to the existing block text below. Change tone, structure, length, or style as instructed — but DO NOT invent facts, drop key information, or contradict the source. Preserve any natural mentions of the focus keyword. Return ONLY the resulting block text.\n\nInstruction:\n{$command}\n\nExisting text:\n{$text}"
                    : $seoContext."Write a single block of text fulfilling the user's instruction. Aim it at the search intent for the focus keyword (if provided). Be concrete and useful. Keep it concise and self-contained.\n\nInstruction:\n{$command}",
            ],
            self::MODE_EXTEND => [
                $system,
                $seoContext."Continue the following text with one or two more paragraphs of closely related content that deepens topical coverage. CRITICAL: return ONLY the NEW continuation paragraphs. DO NOT include, repeat, paraphrase, or rewrite any part of the existing text — it will be appended automatically by the editor. Your output should read naturally as the next thing the reader sees after the existing text. Add specific, search-useful detail (numbers, examples, sub-aspects of the topic) and naturally include the focus keyword or a semantic variant where it fits — never forced. Do not start with phrases like 'In addition' or 'Furthermore' that signal you are continuing — write as if these are standalone paragraphs.\n\nExisting text (for context only — do not output this):\n{$text}",
            ],
            self::MODE_SUMMARISE => [
                $system,
                $seoContext."Summarise the following text into a single concise paragraph (2–4 sentences) suitable for a meta description, intro paragraph, or excerpt. Preserve the focus keyword (if it appears in the source), the key claims, and any specific names, numbers, or product terms.\n\nText:\n{$text}",
            ],
            self::MODE_GRAMMAR => [
                $system,
                "Proofread the following text. Fix every spelling mistake, typo, grammar error, punctuation error, capitalization error, and obvious style slip. Be thorough — do not skip subtle errors. DO NOT change the meaning, tone, voice, factual content, paragraph structure, or word choice. DO NOT rephrase sentences that are already correct. Preserve all keyword mentions exactly as written. Return only the fully corrected text.\n\nText:\n{$text}",
            ],
            self::MODE_REWRITE => [
                $system,
                $seoContext."Rewrite the following text into a stronger version: clearer, more engaging, tighter, and better aligned to the search intent for the focus keyword. Keep the same meaning, claims, and approximate length. Improve specificity (replace vague phrases with concrete ones), add natural semantic variants of the focus keyword where helpful, and trim filler. Do not invent facts. Return only the rewritten text.\n\nText:\n{$text}",
            ],
            default => [$system, $text],
        };
    }

    private function temperatureFor(string $mode): float
    {
        return match ($mode) {
            self::MODE_GRAMMAR => 0.1,
            self::MODE_SUMMARISE => 0.3,
            self::MODE_REWRITE, self::MODE_EXTEND => 0.6,
            self::MODE_COMMAND => 0.7,
            default => 0.5,
        };
    }

    private function stripCodeFences(string $text): string
    {
        $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*\n?/', '', $text) ?? $text;
        $text = preg_replace('/\n?```\s*$/', '', $text) ?? $text;

        return trim($text);
    }
}
