<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ImproveReadability extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'improve-readability',
            name: 'Improve Readability',
            category: Categories::IMPROVEMENT,
            description: 'Lower reading effort. Shorter sentences, plainer words, better flow — same meaning.',
            inputs: [
                new InputField('text', 'Text to improve', 'textarea', required: true, maxLength: 8000),
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
        return "Improve readability: shorter sentences (avg 14–18 words), simpler vocabulary, active voice, better transitions between sentences. Preserve every claim and the original brand voice. Output prose only.\n\n---\n{$text}\n---";
    }
}
