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
    public const MODE_SHORTER = 'shorter';
    public const MODE_LONGER = 'longer';
    public const MODE_TRANSLATE = 'translate';
    public const MODE_TONE = 'tone';
    public const MODE_CONVERT_TO_LIST = 'convert_to_list';
    public const MODE_CONVERT_TO_TABLE = 'convert_to_table';
    public const MODE_TITLE = 'title';

    public const MODES = [
        self::MODE_COMMAND,
        self::MODE_EXTEND,
        self::MODE_SUMMARISE,
        self::MODE_GRAMMAR,
        self::MODE_REWRITE,
        self::MODE_SHORTER,
        self::MODE_LONGER,
        self::MODE_TRANSLATE,
        self::MODE_TONE,
        self::MODE_CONVERT_TO_LIST,
        self::MODE_CONVERT_TO_TABLE,
        self::MODE_TITLE,
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
     *     target_language?: string|null,
     *     tone?: string|null,
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
        $targetLanguage = trim((string) ($input['target_language'] ?? ''));
        $tone = trim((string) ($input['tone'] ?? ''));
        $additionalKeywords = array_values(array_filter(
            array_map(static fn ($k) => trim((string) $k), (array) ($input['additional_keywords'] ?? [])),
            static fn (string $k): bool => $k !== '',
        ));

        $needsText = in_array($mode, [
            self::MODE_EXTEND, self::MODE_SUMMARISE, self::MODE_GRAMMAR, self::MODE_REWRITE,
            self::MODE_SHORTER, self::MODE_LONGER, self::MODE_TRANSLATE, self::MODE_TONE,
            self::MODE_CONVERT_TO_LIST, self::MODE_CONVERT_TO_TABLE,
        ], true);
        // Title mode is a special case: it needs EITHER content text OR
        // the focus keyword to produce useful suggestions.
        if ($mode === self::MODE_TITLE && $text === '' && $focusKeyword === '') {
            return ['ok' => false, 'error' => 'missing_title_inputs'];
        }
        if ($needsText && $text === '') {
            return ['ok' => false, 'error' => 'missing_text'];
        }
        if ($mode === self::MODE_COMMAND && $command === '') {
            return ['ok' => false, 'error' => 'missing_command'];
        }
        if ($mode === self::MODE_TRANSLATE && $targetLanguage === '') {
            return ['ok' => false, 'error' => 'missing_target_language'];
        }
        if ($mode === self::MODE_TONE && $tone === '') {
            return ['ok' => false, 'error' => 'missing_tone'];
        }

        // Cache-only lookup — never triggers a fresh brief run. We only
        // enrich when the page already has one cached; otherwise we degrade
        // gracefully to focus-keyword-only context.
        $brief = $mode !== self::MODE_GRAMMAR && $focusKeyword !== ''
            ? ($this->briefService->cachedBrief($website, $focusKeyword) ?? null)
            : null;
        $briefContext = $this->extractBriefContext($brief);

        [$system, $user] = $this->buildPrompt($mode, $text, $command, $focusKeyword, $title, $additionalKeywords, $briefContext, $targetLanguage, $tone);

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

        // Title mode emits one suggestion per line. Two hard rules:
        //   1. Must contain the EXACT focus keyword (Unicode-quote tolerant).
        //   2. Must be 30–60 chars (Yoast's industry-standard SEO range).
        // Models drift on both — filter rather than serve broken suggestions.
        if ($mode === self::MODE_TITLE) {
            $lines = preg_split('/\r?\n/', $generated) ?: [];
            $kept = [];
            $rejectedKeyword = 0;
            $rejectedLength = 0;
            foreach ($lines as $line) {
                $clean = trim($line);
                if ($clean === '') continue;
                if ($focusKeyword !== '' && ! $this->lineContainsKeyword($clean, $focusKeyword)) {
                    $rejectedKeyword++;
                    continue;
                }
                $len = mb_strlen($clean);
                if ($len < 30 || $len > 60) {
                    $rejectedLength++;
                    continue;
                }
                $kept[] = $clean;
            }
            if (count($kept) === 0) {
                if ($rejectedKeyword > 0 && $rejectedLength === 0) {
                    return ['ok' => false, 'error' => 'focus_keyword_missing', 'message' => 'The model returned title suggestions that did not contain your focus keyword. Try regenerating.'];
                }
                if ($rejectedLength > 0 && $rejectedKeyword === 0) {
                    return ['ok' => false, 'error' => 'length_out_of_range', 'message' => 'The model returned title suggestions outside the 30–60 character window. Try regenerating.'];
                }
                return ['ok' => false, 'error' => 'titles_invalid', 'message' => 'The model returned title suggestions that failed our SEO rules. Try regenerating.'];
            }
            $generated = implode("\n", $kept);
        }

        return ['ok' => true, 'text' => $generated];
    }

    /**
     * Case-insensitive verbatim keyword check, tolerant of Unicode quote
     * / dash variants the model often substitutes. Mirrors the
     * AiSnippetRewriterService validator so both AI title surfaces apply
     * the same standard.
     */
    private function lineContainsKeyword(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $normalize = static function (string $s): string {
            $s = (string) preg_replace('/\x{00A0}/u', ' ', $s);
            $s = (string) preg_replace('/[\x{2018}-\x{201B}]/u', "'", $s);
            $s = (string) preg_replace('/[\x{201C}-\x{201F}]/u', '"', $s);
            $s = (string) preg_replace('/[\x{2013}\x{2014}\x{2212}]/u', '-', $s);
            $s = mb_strtolower($s);
            $s = (string) preg_replace('/\s+/u', ' ', $s);
            return trim($s);
        };
        return mb_strpos($normalize($haystack), $normalize($needle)) !== false;
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
    private function buildPrompt(string $mode, string $text, string $command, string $focusKeyword = '', string $title = '', array $additionalKeywords = [], ?array $briefContext = null, string $targetLanguage = '', string $tone = ''): array
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
            self::MODE_SHORTER => [
                $system,
                $seoContext."Make the following text 30–50% shorter. Preserve all key facts, the focus keyword, named entities, and specific numbers. Remove filler, redundancy, and adjectives that don't add information. Maintain the same tone. Return only the shortened text.\n\nText:\n{$text}",
            ],
            self::MODE_LONGER => [
                $system,
                $seoContext."Expand the following text by 30–50% with relevant, search-useful detail (specifics, examples, named entities, sub-aspects of the topic). Preserve the existing meaning and tone — do not contradict or replace what's there. Naturally include the focus keyword or a semantic variant where it fits. Do not invent facts. Return only the expanded text.\n\nText:\n{$text}",
            ],
            self::MODE_TRANSLATE => [
                $system,
                "Translate the following text to {$targetLanguage}. Preserve names, brand terms, URLs, code, numbers, and HTML tags exactly as written. Match the natural style and idiom of native {$targetLanguage} speakers — do not produce a literal word-for-word translation. Return only the translated text.\n\nText:\n{$text}",
            ],
            self::MODE_TONE => [
                $system,
                $seoContext."Rewrite the following text in a {$tone} tone. Keep the meaning, claims, approximate length, and the focus keyword. Adjust word choice, sentence rhythm, and openness/formality to fit a {$tone} register. Do not invent facts. Return only the rewritten text.\n\nText:\n{$text}",
            ],
            self::MODE_CONVERT_TO_LIST => [
                $system,
                $seoContext."Restructure the following text into a clear bulleted list using <ul><li>…</li></ul> HTML. Each <li> should be a self-contained point — short enough to scan, long enough to be useful. Preserve all key facts and the focus keyword. Group closely-related items together. If the text is genuinely a sequence of steps, use <ol><li>…</li></ol> instead. Return only the resulting HTML.\n\nText:\n{$text}",
            ],
            self::MODE_CONVERT_TO_TABLE => [
                $system,
                $seoContext."Restructure the following text into a comparison table using <table><thead><tr><th>…</th></tr></thead><tbody><tr><td>…</td></tr></tbody></table> HTML. Pick logical column headers based on what's being compared in the source. If the text doesn't have parallel comparable items, infer the most useful columns (e.g., Feature / Description / Benefit) and decompose the prose into rows. Preserve all key facts. Return only the table HTML — no preamble paragraph.\n\nText:\n{$text}",
            ],
            self::MODE_TITLE => [
                $system,
                $seoContext."Generate exactly 5 SEO-optimized title suggestions for this page. Each title MUST:\n"
                    ."- Be 30–60 characters inclusive (Yoast's industry-standard SEO range; Google truncates anything longer than ~60 on SERPs). Count every character — letters, digits, spaces, punctuation. Aim for 50–60 as the sweet spot. Verify the count BEFORE returning each title and rewrite if outside range\n"
                    ."- Contain the EXACT focus keyword verbatim (case-insensitive match) — no paraphrases, no synonyms, no partial matches. The full phrase must appear, ideally in the first 60 characters\n"
                    ."- Match the search intent indicated by the SEO context\n"
                    ."- Be compelling and click-worthy without resorting to clickbait or vague hype\n"
                    ."- Use sentence case (only first letter capitalized) unless a brand name requires capitals\n"
                    ."- Avoid quotation marks, brackets, or trailing punctuation\n"
                    ."- Lean on at most one or two power words where they make the title sharper "
                        ."(e.g., proven, essential, complete, exclusive, fast, easy, definitive, avoid, guide, ultimate, expert, tested, free) — "
                        ."never more than two, never stuffed, never sacrificing clarity for the boost\n\n"
                    ."The 5 titles MUST be different in style:\n"
                    ."  1. Straightforward / informational\n"
                    ."  2. Question form\n"
                    ."  3. Number-led or year-led (e.g., '7 things…' or '… in 2026')\n"
                    ."  4. Benefit-focused (lead with the user outcome)\n"
                    ."  5. Curiosity / contrast-driven\n\n"
                    ."Return ONLY the 5 titles, one per line. No numbering, no quotes, no commentary, no preamble.\n\n"
                    .($text !== '' ? "Page content excerpt for context:\n{$text}" : "(No page content yet — base titles on the focus keyword and SEO context only.)"),
            ],
            default => [$system, $text],
        };
    }

    private function temperatureFor(string $mode): float
    {
        return match ($mode) {
            self::MODE_GRAMMAR, self::MODE_TRANSLATE => 0.1,
            self::MODE_SUMMARISE, self::MODE_SHORTER, self::MODE_CONVERT_TO_LIST, self::MODE_CONVERT_TO_TABLE => 0.3,
            self::MODE_REWRITE, self::MODE_EXTEND, self::MODE_LONGER, self::MODE_TONE => 0.6,
            self::MODE_COMMAND, self::MODE_TITLE => 0.7,
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
