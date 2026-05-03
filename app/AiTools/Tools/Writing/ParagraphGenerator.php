<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ParagraphGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'paragraph-generator',
            name: 'Paragraph Generator',
            category: Categories::WRITING,
            description: 'A single paragraph, in your brand voice.',
            inputs: [
                new InputField('prompt', 'What should the paragraph cover?', 'textarea',
                    required: true, maxLength: 1500),
                new InputField('words', 'Target word count', 'number', default: 90),
            ],
            outputType: 'text',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $prompt = (string) ($input['prompt'] ?? '');
        $words = max(30, min(300, (int) ($input['words'] ?? 90)));

        return "Write ONE paragraph (~{$words} words) about: {$prompt}\n"
            . "No heading, no bullets, no preamble — just the paragraph as plain prose.";
    }
}
