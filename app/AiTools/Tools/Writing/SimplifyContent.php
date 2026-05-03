<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SimplifyContent extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'simplify-content',
            name: 'Simplify Content',
            category: Categories::WRITING,
            description: 'Lower the reading level. Replace jargon with plain words; keep meaning intact.',
            inputs: [
                new InputField('text', 'Text to simplify', 'textarea', required: true, maxLength: 8000),
                new InputField('target_grade', 'Target reading grade', 'select', options: [
                    ['value' => '6', 'label' => 'Grade 6 — easy'],
                    ['value' => '8', 'label' => 'Grade 8 — standard web'],
                    ['value' => '10', 'label' => 'Grade 10 — informed reader'],
                ], default: '8'),
            ],
            outputType: 'text',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $grade = (string) ($input['target_grade'] ?? '8');

        return "Rewrite this passage at a reading-grade level of approximately {$grade}. Use plain Anglo-Saxon vocabulary; replace jargon with everyday words; shorten sentences (avg ~14 words). Keep every fact intact. Output prose only.\n\n---\n{$text}\n---";
    }
}
