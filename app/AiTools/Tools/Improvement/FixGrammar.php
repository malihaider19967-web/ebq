<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class FixGrammar extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'fix-grammar',
            name: 'Fix Grammar & Spelling',
            category: Categories::IMPROVEMENT,
            description: 'Fix grammar, spelling, and obvious style issues — keep the brand voice intact.',
            inputs: [
                new InputField('text', 'Text to fix', 'textarea', required: true, maxLength: 8000),
            ],
            outputType: 'text',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph', 'core/heading', 'core/list', 'core/quote'],
        );
    }

    protected function llmOptions(): array
    {
        return ['temperature' => 0.2, 'max_tokens' => 1500, 'timeout' => 45];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        return "Fix grammar, spelling, and unambiguous style errors in the passage below. Do NOT rewrite the voice. Do NOT alter meaning. Keep the same length within ±5%. Output the corrected passage only.\n\n---\n{$text}\n---";
    }
}
