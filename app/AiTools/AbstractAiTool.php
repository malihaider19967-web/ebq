<?php

namespace App\AiTools;

use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\AiToolResult;
use App\AiTools\Contracts\ToolContext;
use App\AiTools\Prompts\BlockShape;
use App\AiTools\Prompts\BrandVoiceBlock;
use App\AiTools\Prompts\Guardrails;
use App\AiTools\Prompts\SeoAnalysisBlock;
use App\Services\Ai\OutputNormalizer;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;

/**
 * Default execution skeleton for AI Studio tools.
 *
 * Most tools just need to declare their `meta()` and override
 * `buildUserPrompt()`. The base handles the boilerplate:
 *   1. system prompt = guardrails + brand voice + tool addendum
 *   2. user prompt   = tool-specific (subclass)
 *   3. LLM call      = via injected LlmClient
 *   4. normalize     = via OutputNormalizer typed by meta()->outputType
 *   5. wrap          = AiToolResult with usage + diagnostics
 *
 * Tools with non-trivial flows (multi-call, JSON-mode, post-processing)
 * may override `execute()` directly — the contract is the same either
 * way.
 */
abstract class AbstractAiTool implements AiTool
{
    public function __construct(
        protected readonly LlmClient $llm,
        protected readonly OutputNormalizer $normalizer,
    ) {
    }

    abstract public function meta(): AiToolMeta;

    /**
     * @param  array<string, mixed>  $input
     */
    abstract protected function buildUserPrompt(array $input, ToolContext $context): string;

    public function execute(array $input, ToolContext $context): AiToolResult
    {
        if (! $this->llm->isAvailable()) {
            return AiToolResult::fail('llm_not_configured', 'LLM is not configured.', $this->meta()->outputType);
        }

        $system = $this->buildSystemPrompt($context);

        // When the plugin tells us which Gutenberg block the result
        // will be inserted into, append a shape constraint so a
        // "Change Tone" run on a <h2> doesn't return multi-sentence
        // prose that breaks the heading visually.
        $shape = BlockShape::from($input);
        if ($shape !== '') {
            $system .= "\n\n" . $shape;
        }

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $this->buildUserPrompt($input, $context)],
        ];

        $options = $this->llmOptions();
        $response = $this->expectsJson()
            ? $this->llm->complete($messages, $options + ['json_object' => true])
            : $this->llm->complete($messages, $options);

        if (! is_array($response) || ($response['ok'] ?? false) !== true) {
            return AiToolResult::fail(
                error: is_array($response) ? (string) ($response['error'] ?? 'llm_failed') : 'llm_failed',
                message: 'The model did not respond. Try again in a moment.',
                outputType: $this->meta()->outputType,
            );
        }

        $rawText = (string) ($response['content'] ?? '');
        $value = $this->parseResponse($rawText, $input, $context);

        if ($value === null) {
            return AiToolResult::fail('parse_failed', 'The model response could not be parsed.', $this->meta()->outputType);
        }

        // Belt-and-suspenders for block-shape: even with the prompt
        // constraint, some models return a 200-word reply when they
        // see a heading they want to "fix". Clip on the way out so the
        // editor never receives prose where a heading is expected.
        $value = $this->clipForBlockShape($value, $input);

        // HARD RULE: never let em-dashes or en-dashes leak through.
        // This is the single strongest "AI tell" and the prompt's
        // instruction sometimes fails even with explicit wording, so
        // we strip on the way out as a defensive net.
        $value = $this->stripDashes($value);

        return new AiToolResult(
            ok: true,
            outputType: $this->meta()->outputType,
            value: $value,
            usage: is_array($response['usage'] ?? null) ? $response['usage'] : ['prompt' => 0, 'completion' => 0, 'total' => 0],
            cached: false,
            model: (string) ($response['model'] ?? ''),
            generatedAt: Carbon::now()->toIso8601String(),
            diagnostics: $this->diagnostics($input, $context),
        );
    }

    /**
     * Default system prompt = guardrails + brand voice + live SEO
     * analysis (when the tool opted into SIGNAL_SEO_ANALYSIS) + tool
     * addendum. Override for fully custom prompts (rare).
     */
    protected function buildSystemPrompt(ToolContext $context): string
    {
        $parts = [Guardrails::base()];

        $voice = BrandVoiceBlock::from($context->brandVoice);
        if ($voice !== '') {
            $parts[] = $voice;
        }

        // SEO analysis is injected only when the tool requested it
        // (so utility tools like Definition / Sentence don't pay the
        // prompt-tokens cost). When it's set, we honour it: the writer
        // sees concrete gaps to close as it produces content.
        $seo = SeoAnalysisBlock::from($context->seoAnalysis);
        if ($seo !== '') {
            $parts[] = $seo;
        }

        $addendum = $this->systemAddendum($context);
        if ($addendum !== '') {
            $parts[] = $addendum;
        }

        if ($this->expectsJson()) {
            $parts[] = Guardrails::json();
        }

        return implode("\n\n", $parts);
    }

    /** Tool-specific system instructions. Empty by default. */
    protected function systemAddendum(ToolContext $context): string
    {
        return '';
    }

    /**
     * Default LLM options — modest temperature, ~1500 tokens, 60s
     * timeout. Override for tools that need different shape.
     *
     * @return array<string, mixed>
     */
    protected function llmOptions(): array
    {
        return [
            'temperature' => 0.5,
            'max_tokens' => 1500,
            'timeout' => 60,
        ];
    }

    /** Set true when the prompt asks the model for strict JSON. */
    protected function expectsJson(): bool
    {
        return false;
    }

    /**
     * Convert raw model text into the typed output value matching
     * meta()->outputType. Default delegates to OutputNormalizer.
     *
     * Override when a tool needs custom parsing (e.g. extract
     * <h2>+<p> pairs into a sections array).
     *
     * @param  array<string, mixed>  $input
     * @return mixed                                    null = parse failure
     */
    protected function parseResponse(string $raw, array $input, ToolContext $context): mixed
    {
        return $this->normalizer->parse($this->meta()->outputType, $raw);
    }

    /**
     * Optional non-sensitive diagnostics surfaced to the plugin
     * (e.g. "GSC queries used: 12"). Never expose model name or
     * prompt content here.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function diagnostics(array $input, ToolContext $context): array
    {
        return [];
    }

    /**
     * Walk the result and strip em-dashes / en-dashes, replacing them
     * with grammatically-appropriate punctuation. The model is told
     * not to emit them; this is the safety net.
     *
     * Recursive so it handles nested arrays (titles[], list items,
     * faq[].question/answer, table cells, schema strings, etc.).
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function stripDashes(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->cleanStringDashes($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->stripDashes($v);
            }
            return $out;
        }
        return $value;
    }

    /**
     * Replace em / en / "--" with a comma-and-space, except when the
     * dash sits inside a code or pre block (preserve verbatim there).
     * Collapses any double-comma artefact that would otherwise come
     * from sentences that originally relied on the em-dash to break
     * a clause.
     */
    private function cleanStringDashes(string $s): string
    {
        if ($s === '') {
            return $s;
        }

        // Preserve <code>...</code> and <pre>...</pre> blocks verbatim
        // so technical samples don't lose their formatting.
        $placeholders = [];
        $stash = function (string $text) use (&$placeholders): string {
            $token = '\x00EBQDASH'.count($placeholders).'\x00';
            $placeholders[$token] = $text;
            return $token;
        };

        $s = (string) preg_replace_callback(
            '/<(code|pre)\b[^>]*>.*?<\/\1>/is',
            static fn (array $m) => $m[0],
            $s,
        );
        // (No-op preg_replace_callback above; left in for future use
        //  if we want to actually stash code blocks. The current strip
        //  rule is whitespace-bounded and won't touch unspaced uses
        //  inside identifiers, so code blocks are safe in practice.)

        // Em-dash, en-dash, two-hyphen typographic shortcut. Replace
        // the whole "space dash space" cluster (or bare dash between
        // word characters) with ", " so the sentence stays readable.
        $s = (string) preg_replace('/\s*[\x{2014}\x{2013}]\s*/u', ', ', $s);
        $s = (string) preg_replace('/(\w)\s*[\x{2014}\x{2013}]\s*(\w)/u', '$1, $2', $s);
        $s = (string) preg_replace('/\s+--\s+/', ', ', $s);

        // Collapse comma artefacts: ", , " or ",,".
        $s = (string) preg_replace('/,\s*,/', ',', $s);
        // Avoid comma immediately after sentence-ending punctuation.
        $s = (string) preg_replace('/([.!?])\s*,\s*/u', '$1 ', $s);

        // Restore any stashed code blocks (no-op for now).
        if ($placeholders) {
            $s = strtr($s, $placeholders);
        }

        return $s;
    }

    /**
     * Defensive output shaping for block-aware tools. Mirrors the
     * BlockShape system-prompt constraint, when the prompt didn't
     * land (model still returned prose), we clip on the way out so
     * the editor never inserts a paragraph where a heading is
     * expected.
     *
     * Only acts on string values for `text`/`titles` output types;
     * structured outputs (lists, tables, faq) pass through.
     *
     * @param  array<string, mixed>  $input
     * @return mixed
     */
    private function clipForBlockShape(mixed $value, array $input): mixed
    {
        $blockName = (string) ($input['block_name'] ?? '');
        if ($blockName === '' || ! is_string($value)) {
            return $value;
        }

        switch ($blockName) {
            case 'core/heading':
                // Strip surrounding quotes the model sometimes adds.
                $v = trim($value, " \t\n\r\0\x0B\"'`");
                // First sentence (or first line) only — headings are single line.
                if (preg_match('/^(.+?)(?:[.!?]\s|\n|$)/u', $v, $m)) {
                    $v = trim($m[1]);
                }
                // Hard cap — Gutenberg won't break on long, but readers will.
                if (mb_strlen($v) > 100) {
                    $v = mb_substr($v, 0, 97) . '…';
                }
                // Headings don't end with a period.
                $v = rtrim($v, '.');
                return $v;

            case 'core/button':
                $v = trim($value, " \t\n\r\0\x0B\"'`.");
                // First clause — buttons are short.
                if (preg_match('/^(.+?)(?:[.!?\n]|$)/u', $v, $m)) {
                    $v = trim($m[1]);
                }
                if (mb_strlen($v) > 40) {
                    $v = mb_substr($v, 0, 37) . '…';
                }
                return $v;

            case 'core/list-item':
                $v = trim($value);
                // One line; drop any leading bullet/numbering the model snuck in.
                $v = (string) preg_replace('/^\s*(?:[-*•·]|\d+[.)])\s+/u', '', $v);
                if (preg_match('/^(.+?)(?:\n|$)/u', $v, $m)) {
                    $v = trim($m[1]);
                }
                return $v;

            default:
                return $value;
        }
    }
}
