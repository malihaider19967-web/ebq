<?php

namespace App\AiTools\Tools\Misc;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ListGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'list-generator',
            name: 'List Generator',
            category: Categories::MISC,
            description: 'A bulleted list on any topic.',
            inputs: [
                new InputField('topic', 'Topic', 'text', required: true, maxLength: 200),
                new InputField('count', 'How many items?', 'number', default: 8),
                new InputField('item_format', 'Item format', 'select', options: [
                    ['value' => 'short', 'label' => 'Short — single phrase'],
                    ['value' => 'phrase_dash_explanation', 'label' => 'Phrase — short explanation'],
                ], default: 'phrase_dash_explanation'),
            ],
            outputType: 'list',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/list'],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $topic = (string) ($input['topic'] ?? '');
        $count = max(3, min(20, (int) ($input['count'] ?? 8)));
        $format = (string) ($input['item_format'] ?? 'phrase_dash_explanation');

        $shape = $format === 'short'
            ? 'Each line is a single short phrase (≤6 words).'
            : "Each line is 'Phrase — one sentence explanation.'";

        return "Topic: {$topic}\n\nGenerate a list of {$count} items. {$shape} One per line, no numbering or bullets.";
    }
}
