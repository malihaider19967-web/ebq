<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class Summarizer extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'summarizer',
            name: 'Summarizer',
            category: Categories::IMPROVEMENT,
            description: 'TL;DR or N-bullet summary of any passage.',
            inputs: [
                new InputField('text', 'Text', 'textarea', required: true, maxLength: 16000),
                new InputField('format', 'Format', 'select', options: [
                    ['value' => 'tldr', 'label' => 'TL;DR (1–2 sentences)'],
                    ['value' => 'bullets', 'label' => 'Bullets'],
                    ['value' => 'paragraph', 'label' => 'Short paragraph'],
                ], default: 'bullets'),
                new InputField('count', 'Bullet count (if bullets)', 'number', default: 5),
            ],
            outputType: 'text',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph', 'core/list'],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $format = (string) ($input['format'] ?? 'bullets');
        $count = max(3, min(10, (int) ($input['count'] ?? 5)));

        $instr = match ($format) {
            'tldr' => 'Write a 1–2 sentence TL;DR. Plain prose.',
            'paragraph' => 'Write a 60–90 word paragraph summary.',
            default => "Write {$count} concise bullets. One per line, prefix each with '- '.",
        };

        return "Summarise the passage:\n\n---\n{$text}\n---\n\n{$instr}";
    }
}
