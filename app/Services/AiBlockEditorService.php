<?php

namespace App\Services;

use App\Models\Website;
use App\Services\Llm\LlmClient;

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

    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * @param  array{mode?: string, text?: string|null, command?: string|null}  $input
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

        $needsText = in_array($mode, [self::MODE_EXTEND, self::MODE_SUMMARISE, self::MODE_GRAMMAR, self::MODE_REWRITE], true);
        if ($needsText && $text === '') {
            return ['ok' => false, 'error' => 'missing_text'];
        }
        if ($mode === self::MODE_COMMAND && $command === '') {
            return ['ok' => false, 'error' => 'missing_command'];
        }

        [$system, $user] = $this->buildPrompt($mode, $text, $command);

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
     * @return array{0: string, 1: string} [systemPrompt, userPrompt]
     */
    private function buildPrompt(string $mode, string $text, string $command): array
    {
        $system = 'You are an expert content editor working inside a single block of a WordPress Gutenberg editor. Return ONLY the resulting block text — no preamble, no explanations, no markdown code fences. Match the input language. Keep formatting minimal: plain prose, no lists or headings unless the input clearly required them.';

        return match ($mode) {
            self::MODE_COMMAND => [
                $system,
                "Write a single block of text fulfilling the following instruction. Keep it concise and self-contained.\n\nInstruction:\n{$command}",
            ],
            self::MODE_EXTEND => [
                $system,
                "Extend the following text with one or two more paragraphs of closely related content. The existing text MUST appear unchanged at the very start of your output. Do not repeat sentences from the existing text in the new portion.\n\nExisting text:\n{$text}",
            ],
            self::MODE_SUMMARISE => [
                $system,
                "Summarise the following text into a single concise paragraph (2–4 sentences). Preserve the key claims and any specific names, numbers, or product terms.\n\nText:\n{$text}",
            ],
            self::MODE_GRAMMAR => [
                $system,
                "Fix grammar, spelling, punctuation, and obvious style errors in the following text. Do NOT change the meaning, tone, voice, or content. Return only the corrected text.\n\nText:\n{$text}",
            ],
            self::MODE_REWRITE => [
                $system,
                "Rewrite the following text into a stronger version: clearer, more engaging, tighter — but keep the same meaning, claims, and approximate length. Do not invent facts. Return only the rewritten text.\n\nText:\n{$text}",
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
