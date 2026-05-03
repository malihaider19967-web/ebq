<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ChangeTone extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'change-tone',
            name: 'Change Tone',
            category: Categories::IMPROVEMENT,
            description: 'Shift the tone of a passage without changing the facts.',
            inputs: [
                new InputField('text', 'Text', 'textarea', required: true, maxLength: 8000),
                new InputField('target_tone', 'Target tone', 'select', required: true, options: [
                    ['value' => 'formal', 'label' => 'Formal'],
                    ['value' => 'casual', 'label' => 'Casual'],
                    ['value' => 'empathetic', 'label' => 'Empathetic'],
                    ['value' => 'authoritative', 'label' => 'Authoritative'],
                    ['value' => 'playful', 'label' => 'Playful'],
                    ['value' => 'urgent', 'label' => 'Urgent'],
                    ['value' => 'concise', 'label' => 'Concise / no-nonsense'],
                ], default: 'authoritative'),
            ],
            outputType: 'text',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph', 'core/heading'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $tone = (string) ($input['target_tone'] ?? 'authoritative');

        return "Rewrite this passage with a {$tone} tone. Keep every claim intact. Output prose only — no commentary.\n\n---\n{$text}\n---";
    }
}
