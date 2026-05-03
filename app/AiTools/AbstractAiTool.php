<?php

namespace App\AiTools;

use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\AiToolResult;
use App\AiTools\Contracts\ToolContext;
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

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($context)],
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
}
