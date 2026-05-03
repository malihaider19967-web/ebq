<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ShortenContent extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'shorten-content',
            name: 'Shorten Content',
            category: Categories::WRITING,
            description: 'Trim verbose passage. Keep every claim, drop the filler.',
            inputs: [
                new InputField('text', 'Text to shorten', 'textarea', required: true, maxLength: 8000),
                new InputField('reduction_pct', 'Reduce by (%)', 'number', default: 40),
            ],
            outputType: 'text',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        $pct = max(10, min(80, (int) ($input['reduction_pct'] ?? 40)));

        return "Shorten the passage by approximately {$pct}%. Keep every concrete claim and example; drop transitional fluff and adjective stacks. Output prose only.\n\n---\n{$text}\n---";
    }
}
