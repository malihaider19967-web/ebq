<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class KeyPoints extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'key-points',
            name: 'Key Points Extractor',
            category: Categories::IMPROVEMENT,
            description: 'Pull the load-bearing claims out of a passage as a clean bulleted list.',
            inputs: [
                new InputField('text', 'Text', 'textarea', required: true, maxLength: 16000),
                new InputField('count', 'How many points?', 'number', default: 5),
            ],
            outputType: 'list',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/list'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $count = max(3, min(12, (int) ($input['count'] ?? 5)));

        return "Extract {$count} key points from this passage. Each point ≤20 words. One per line, no numbering or bullets — just the point text on each line.\n\n---\n{$text}\n---";
    }
}
