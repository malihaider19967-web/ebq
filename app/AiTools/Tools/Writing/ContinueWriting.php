<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

/**
 * Lightweight "continue this sentence" tool — drives the in-editor
 * ghost-text autocomplete. Throttled at the plugin to one call every
 * ~1.2s of typing pause, max 80 output tokens, so it stays cheap and
 * latency-friendly.
 *
 * Brand voice + live SEO analysis are still injected so the suggestion
 * sounds like the user wrote it AND nudges toward closing the
 * keyword/density/structure gaps.
 */
final class ContinueWriting extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'continue-writing',
            name: 'Continue Writing',
            category: Categories::WRITING,
            description: 'Suggests the next ~1 sentence of prose. Powers the in-editor ghost text.',
            inputs: [
                new InputField('text_before', 'Text before cursor', 'textarea', required: true, maxLength: 4000),
            ],
            outputType: 'text',
            estCredits: 1,
            surfaces: [AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function llmOptions(): array
    {
        // Tight token budget — ghost text is one short sentence, not a
        // paragraph. Higher temperature gives the suggestion variety.
        return [
            'temperature' => 0.6,
            'max_tokens' => 100,
            'timeout' => 25,
        ];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $before = trim((string) ($input['text_before'] ?? ''));
        // Keep prompt tight — only the trailing ~600 chars matter for
        // continuation; prefixing with the full draft would pad latency.
        $tail = mb_substr($before, max(0, mb_strlen($before) - 600));

        return "The user is in the middle of writing the passage below. Continue with EXACTLY ONE more sentence (8–22 words) that flows naturally from where they stopped. Match their voice, register, and pacing. Do not summarise, do not introduce a heading or list, do not finish the article. Output the next sentence only — no preamble, no quotes, no explanation.\n\nPassage so far:\n---\n{$tail}\n---\n\nNext sentence:";
    }
}
