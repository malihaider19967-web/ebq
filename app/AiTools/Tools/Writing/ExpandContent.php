<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ExpandContent extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'expand-content',
            name: 'Expand Content',
            category: Categories::WRITING,
            description: 'Add depth to a thin passage — examples, evidence, missing nuance.',
            inputs: [
                new InputField('text', 'Text to expand', 'textarea', required: true, maxLength: 8000),
                new InputField('target_words', 'Target word count', 'number', default: 250),
            ],
            outputType: 'text',
            estCredits: 8,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            contextSignals: [AiTool::SIGNAL_BRIEF],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $target = max(80, min(800, (int) ($input['target_words'] ?? 250)));

        $brief = '';
        if (is_array($context->cachedBrief)) {
            $sub = (array) ($context->cachedBrief['subtopics'] ?? []);
            if ($sub !== []) {
                $brief = "\n\nIf relevant, weave these subtopics in:\n- " . implode("\n- ", array_slice($sub, 0, 6));
            }
        }

        return "Expand the passage to ~{$target} words. Add concrete examples, missing context, or evidence — don't pad with filler. Keep the original meaning intact. Output prose only.\n\n---\n{$text}\n---"
            . $brief;
    }
}
