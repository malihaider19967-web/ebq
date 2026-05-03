<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class RewriteContent extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'rewrite-content',
            name: 'Rewrite Content',
            category: Categories::WRITING,
            description: 'Rewrite a passage in the same meaning — clearer, tighter, on-brand.',
            inputs: [
                new InputField('text', 'Text to rewrite', 'textarea', required: true, maxLength: 8000),
            ],
            outputType: 'text',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph', 'core/quote', 'core/list'],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');
        return "Rewrite the following passage. Preserve EVERY claim and the same approximate length. Improve clarity, remove filler, and match the brand voice. Output the rewritten passage only — no commentary.\n\n---\n{$text}\n---";
    }
}
