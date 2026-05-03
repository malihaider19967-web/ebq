<?php

namespace App\AiTools\Tools\Misc;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class Rephraser extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'rephraser',
            name: 'Rephraser',
            category: Categories::MISC,
            description: '3 sentence-level rewrites preserving meaning.',
            inputs: [
                new InputField('text', 'Sentence', 'textarea', required: true, maxLength: 1000),
                new InputField('count', 'How many variants?', 'number', default: 3),
            ],
            outputType: 'list',
            estCredits: 2,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $count = max(2, min(8, (int) ($input['count'] ?? 3)));

        return "Rephrase this sentence in {$count} different ways. Same meaning, different word choices and sentence structure. One per line, no numbering, no quotes.\n\n---\n{$text}\n---";
    }
}
